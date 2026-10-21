<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RequirementController extends Controller
{
    /**
     * List all requirements with submission counts
     */
    public function index()
    {
        // Should probably filter by active semester or all? 
        // For now, let's return all, or just let frontend filter.
        $requirements = Requirement::with('semester')->withCount('submissions')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($requirements);
    }

    /**
     * Create a new requirement
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'semester_id' => ['required', Rule::exists('semesters', 'id')->where('tenant_id', Auth::user()->tenant_id)],
            'is_required' => 'boolean',
            'deadline' => 'nullable|date'
        ]);

        $requirement = Requirement::create([
            'name' => $request->name,
            'description' => $request->description,
            'semester_id' => $request->semester_id,
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
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'semester_id' => ['required', Rule::exists('semesters', 'id')->where('tenant_id', Auth::user()->tenant_id)],
            'is_required' => 'boolean',
            'deadline' => 'nullable|date'
        ]);

        $requirement->update([
            'name' => $request->name,
            'description' => $request->description,
            'semester_id' => $request->semester_id,
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
}
