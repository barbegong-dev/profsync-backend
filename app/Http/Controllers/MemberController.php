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
 * Member management for workspace admins and program chairs.
 * Members sign in with Google, so managers only set name, email, role and department.
 *
 * - Admins manage everyone in their tenant.
 * - Program chairs manage the faculty of their own department, and can approve or reject
 *   pending sign-ups into it. Only admins can add or assign program chairs, deans and admins.
 * - A department can have at most MAX_CHAIRS program chairs.
 */
class MemberController extends Controller
{
    public const MAX_CHAIRS = 2;

    private const ADMIN_ROLES = ['admin', 'dean', 'program_chair', 'faculty'];
    private const CHAIR_ROLES = ['faculty'];

    public function index()
    {
        $query = User::with(['roles', 'department'])
            ->where('tenant_id', $this->tenantId());

        if ($this->isChair()) {
            $departmentId = $this->chairDepartmentId();
            $query->where(function ($q) use ($departmentId) {
                $q->where('department_id', $departmentId)
                    ->orWhere(fn ($p) => $p->where('status', 'pending')->whereNull('department_id'));
            });
        }

        $users = $query
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
        $this->forceChairDepartment($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'role' => ['required', Rule::in($this->allowedRoles())],
            'department_id' => $this->departmentRule($request->role),
        ]);

        if ($error = $this->chairLimitError($request->role, $request->department_id)) {
            return response()->json(['message' => $error], 422);
        }

        $user = User::create([
            'tenant_id' => $this->tenantId(),
            'name' => $request->name,
            'email' => $request->email,
            'department_id' => $request->department_id,
            'status' => 'approved', // Manager added users are pre-approved
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
        $this->authorizeManage($user);
        $this->forceChairDepartment($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => ['required', Rule::in($this->allowedRoles())],
            'department_id' => $this->departmentRule($request->role),
        ]);

        if ($user->id === Auth::id() && $request->role !== 'admin') {
            return response()->json(['message' => 'You cannot remove your own admin role.'], 422);
        }

        if ($error = $this->chairLimitError($request->role, $request->department_id, $user)) {
            return response()->json(['message' => $error], 422);
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
        $this->authorizeManage($user);

        if (!$user->hasRole('admin') && !$user->department_id) {
            return response()->json(['message' => 'Assign a department to this user before approving.'], 422);
        }

        $user->update(['status' => 'approved']);

        $approver = $this->isChair() ? 'your Program Chair' : 'the administrator';
        try {
            Notification::create([
                'user_id' => $user->id,
                'message' => "Congratulations! Your account has been approved by {$approver}. You now have full access to ProfSync.",
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
        $this->authorizeManage($user);

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
        $this->authorizeView($user);

        $activeSemester = \App\Models\Semester::where('is_active', true)->first();

        if (!$activeSemester) {
            return response()->json([]);
        }

        $requirements = \App\Models\Requirement::where('semester_id', $activeSemester->id)
            ->forDepartment($user->department_id)
            ->get();
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

    private function isChair(): bool
    {
        return !Auth::user()->hasRole('admin') && Auth::user()->hasRole('program_chair');
    }

    private function chairDepartmentId(): int
    {
        $departmentId = Auth::user()->department_id;
        abort_unless($departmentId, 403, 'You are not assigned to a department. Ask the workspace admin to assign one.');

        return $departmentId;
    }

    private function allowedRoles(): array
    {
        return $this->isChair() ? self::CHAIR_ROLES : self::ADMIN_ROLES;
    }

    /** Chairs always add and assign members to their own department. */
    private function forceChairDepartment(Request $request): void
    {
        if ($this->isChair()) {
            $request->merge(['department_id' => $this->chairDepartmentId()]);
        }
    }

    /** Chairs may view anyone in their department. */
    private function authorizeView(User $user): void
    {
        abort_if($user->tenant_id !== $this->tenantId(), 404);

        if ($this->isChair()) {
            abort_if($user->department_id !== $this->chairDepartmentId(), 404);
        }
    }

    /** Chairs may change faculty of their department and pending sign-ups that have no department yet. */
    private function authorizeManage(User $user): void
    {
        abort_if($user->tenant_id !== $this->tenantId(), 404);

        if (!$this->isChair()) {
            return;
        }

        $pendingUnassigned = $user->status === 'pending' && $user->department_id === null;
        $ownFaculty = $user->department_id === $this->chairDepartmentId();
        abort_unless($pendingUnassigned || $ownFaculty, 404);

        if (!$user->hasRole('faculty') || $user->hasAnyRole(['admin', 'dean', 'program_chair'])) {
            abort(403, 'Only the workspace admin can manage program chairs, deans and admins.');
        }
    }

    /** A department can have at most MAX_CHAIRS program chairs. */
    private function chairLimitError(?string $role, $departmentId, ?User $except = null): ?string
    {
        if ($role !== 'program_chair' || !$departmentId) {
            return null;
        }

        $chairs = User::role('program_chair')
            ->where('tenant_id', $this->tenantId())
            ->where('department_id', $departmentId)
            ->when($except, fn ($q) => $q->where('id', '!=', $except->id))
            ->count();

        return $chairs >= self::MAX_CHAIRS
            ? 'This department already has ' . self::MAX_CHAIRS . ' program chairs. Change one of them to another role first.'
            : null;
    }

    private function departmentRule(?string $role): array
    {
        return [
            $role === 'admin' ? 'nullable' : 'required',
            Rule::exists('departments', 'id')->where('tenant_id', $this->tenantId()),
        ];
    }
}
