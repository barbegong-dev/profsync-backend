<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Notification;
use App\Support\AccountEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Workspace admin management of the users in their own tenant.
 * Members sign in with Google, so admins only set name, email, role and department.
 */
class AdminUserController extends Controller
{
    private const ROLES = ['admin', 'dean', 'program_chair', 'faculty'];

    public function index()
    {
        $users = User::with(['roles', 'department'])
            ->where('tenant_id', $this->tenantId())
            ->orderByRaw("CASE WHEN status = 'pending' THEN 1 ELSE 2 END")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($users);
    }

    /**
     * Pre-register a member by email. They sign in with the Google account for that email.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => ['required', Rule::in(self::ROLES)],
            'department_id' => $this->departmentRule($request->role),
        ]);

        $user = User::create([
            'tenant_id' => $this->tenantId(),
            'name' => $request->name,
            'email' => $request->email,
            'department_id' => $request->department_id,
            'status' => 'approved', // Admin added users are pre-approved
        ]);

        $user->assignRole($request->role);

        AccountEmails::memberAdded($user);

        return response()->json([
            'success' => true,
            'user' => $user->load('roles', 'department'),
            'message' => 'User added. They can now sign in with Google.'
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeTenant($user);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in(self::ROLES)],
            'department_id' => $this->departmentRule($request->role),
        ]);

        if ($user->id === Auth::id() && $request->role !== 'admin') {
            return response()->json(['message' => 'You cannot remove your own admin role.'], 422);
        }

        $user->load('roles', 'department');
        $oldRole = $user->roles->first()?->name;
        $oldDepartment = $user->department?->name;

        $user->update($request->only('name', 'email', 'department_id'));
        $user->syncRoles([$request->role]);
        $user->load('department');

        // Let active members know when their role or department changes
        // (pending users hear about it in the approval email instead)
        $changes = [];
        if ($oldRole !== $request->role) {
            $changes['Role'] = AccountEmails::roleLabel($oldRole) . ' → ' . AccountEmails::roleLabel($request->role);
        }
        if ($oldDepartment !== $user->department?->name) {
            $changes['Department'] = ($oldDepartment ?? 'None') . ' → ' . ($user->department?->name ?? 'None');
        }
        if ($changes && $user->status === 'approved') {
            AccountEmails::accountUpdated($user, $changes);
        }

        return response()->json([
            'success' => true,
            'user' => $user->load('roles', 'department'),
            'message' => 'User updated successfully'
        ]);
    }

    public function approve(User $user)
    {
        $this->authorizeTenant($user);

        if (!$user->hasRole('admin') && !$user->department_id) {
            return response()->json(['message' => 'Assign a department to this user before approving.'], 422);
        }

        $user->update(['status' => 'approved']);

        try {
            Notification::create([
                'user_id' => $user->id,
                'message' => 'Congratulations! Your account has been approved by the administrator. You now have full access to ProfSync.',
                'read' => false,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create user approval notification: ' . $e->getMessage());
        }

        AccountEmails::approved($user);

        return response()->json([
            'success' => true,
            'message' => 'User approved. They have been notified by email.'
        ]);
    }

    public function destroy(User $user)
    {
        $this->authorizeTenant($user);

        if ($user->id === Auth::id()) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        // Email first: the address is gone once the user is deleted
        $wasPending = $user->status === 'pending';
        AccountEmails::removed($user, $wasPending);

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => $wasPending ? 'Sign-up rejected. They have been notified by email.' : 'User removed. They have been notified by email.',
        ]);
    }

    public function submissions(User $user)
    {
        $this->authorizeTenant($user);

        $activeSemester = \App\Models\Semester::where('is_active', true)->first();

        if (!$activeSemester) {
            return response()->json([]);
        }

        $requirements = \App\Models\Requirement::where('semester_id', $activeSemester->id)->get();
        $submissions = \App\Models\Submission::where('faculty_id', $user->id)
            ->whereIn('requirement_id', $requirements->pluck('id'))
            ->get()
            ->keyBy('requirement_id');

        $checklist = $requirements->map(function ($r) use ($submissions) {
            return [
                'requirement_id' => $r->id,
                'requirement' => $r->name,
                'status' => $submissions[$r->id]->status ?? 'pending',
                'remarks' => $submissions[$r->id]->remarks ?? null,
                'file_path' => $submissions[$r->id]->file_path ?? null,
                'deadline' => $r->deadline,
                'submission_id' => $submissions[$r->id]->id ?? null,
            ];
        });

        return response()->json($checklist);
    }

    private function tenantId(): int
    {
        return Auth::user()->tenant_id;
    }

    private function authorizeTenant(User $user): void
    {
        abort_if($user->tenant_id !== $this->tenantId(), 404);
    }

    private function departmentRule(?string $role): array
    {
        return [
            $role === 'admin' ? 'nullable' : 'required',
            Rule::exists('departments', 'id')->where('tenant_id', $this->tenantId()),
        ];
    }
}
