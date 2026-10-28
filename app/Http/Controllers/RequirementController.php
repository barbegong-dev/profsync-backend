<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Requirements management for workspace admins and program chairs.
 * Admins manage every requirement and choose who it applies to (all departments or one).
 * Chairs see the requirements that apply to their department and manage only their department's own.
 */
class RequirementController extends Controller
{
    /**
     * List requirements with submission counts
     */
    public function index()
    {
        $chairDepartment = $this->chairDepartmentId();

        $requirements = Requirement::with(['semester', 'department'])->withCount('submissions')
            ->when($chairDepartment, fn ($q) => $q->forDepartment($chairDepartment))
            ->orderBy('created_at', 'desc')
            ->get()
            ->each(fn ($r) => $r->setAttribute('can_edit', $this->canEdit($r)));

        return response()->json($requirements);
    }

    /**
     * Create a new requirement
     */
    public function store(Request $request)
    {
        $this->forceChairDepartment($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'semester_id' => ['required', Rule::exists('semesters', 'id')->where('tenant_id', Auth::user()->tenant_id)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', Auth::user()->tenant_id)],
            'is_required' => 'boolean',
            'deadline' => 'nullable|date'
        ]);

        $requirement = Requirement::create([
            'name' => $request->name,
            'description' => $request->description,
            'semester_id' => $request->semester_id,
            'department_id' => $request->department_id,
            'is_required' => $request->is_required ?? true,
            'deadline' => $request->deadline
        ]);

        return response()->json([
            'success' => true,
            'requirement' => $requirement,
            'message' => 'Requirement created successfully'
        ], 201);
    }

    /**
     * Update an existing requirement
     */
    public function update(Request $request, Requirement $requirement)
    {
        $this->authorizeEdit($requirement);
        $this->forceChairDepartment($request);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'semester_id' => ['required', Rule::exists('semesters', 'id')->where('tenant_id', Auth::user()->tenant_id)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', Auth::user()->tenant_id)],
            'is_required' => 'boolean',
            'deadline' => 'nullable|date'
        ]);

        $requirement->update([
            'name' => $request->name,
            'description' => $request->description,
            'semester_id' => $request->semester_id,
            'department_id' => $request->department_id,
            'is_required' => $request->is_required ?? true,
            'deadline' => $request->deadline
        ]);

        return response()->json([
            'success' => true,
            'requirement' => $requirement,
            'message' => 'Requirement updated successfully'
        ]);
    }

    /**
     * Delete a requirement
     */
    public function destroy(Requirement $requirement, Request $request)
    {
        $this->authorizeEdit($requirement);

        $submissionCount = $requirement->submissions()->count();

        // If force delete is requested and user is admin (implied by route middleware usually, but good to check or trust middleware)
        if ($request->query('force') === 'true') {
            $requirement->delete(); // This will cascade delete submissions due to FK constraint if set, or we need to manually delete
            // Since we set onDelete('cascade') in migration, standard delete works. 
            // BUT, if we want to be safe with model events (e.g. file cleanup), we might need to iterate.
            // For now, standard delete is fine.
            return response()->json([
                'success' => true,
                'message' => 'Requirement and all submissions deleted successfully'
            ]);
        }

        if ($submissionCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete requirement with existing submissions. confirmation required',
                'requires_confirmation' => true,
                'submission_count' => $submissionCount
            ], 422);
        }

        $requirement->delete();

        return response()->json([
            'success' => true,
            'message' => 'Requirement deleted successfully'
        ]);
    }

    /** The chair's department, or null for admins. */
    private function chairDepartmentId(): ?int
    {
        $user = Auth::user();
        if ($user->hasRole('admin')) {
            return null;
        }

        abort_unless($user->department_id, 403, 'You are not assigned to a department. Ask the workspace admin to assign one.');
        return $user->department_id;
    }

    private function canEdit(Requirement $requirement): bool
    {
        $chairDepartment = $this->chairDepartmentId();
        return $chairDepartment === null || $requirement->department_id === $chairDepartment;
    }

    private function authorizeEdit(Requirement $requirement): void
    {
        abort_unless($this->canEdit($requirement), 403, 'Only the workspace admin can change requirements that apply to every department.');
    }

    /** Chairs' requirements always belong to their own department. */
    private function forceChairDepartment(Request $request): void
    {
        if ($chairDepartment = $this->chairDepartmentId()) {
            $request->merge(['department_id' => $chairDepartment]);
        }
    }
}
