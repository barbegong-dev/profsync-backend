<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Requirement;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Progress of the workspace setup guide shown to tenant admins.
 */
class SetupController extends Controller
{
    public function status()
    {
        $tenant = Auth::user()->tenant;
        $activeSemester = Semester::where('is_active', true)->first();
        $users = User::where('tenant_id', $tenant->id);

        return response()->json([
            'tenant' => ['name' => $tenant->name, 'slug' => $tenant->slug],
            'departments' => Department::count(),
            'semesters' => Semester::count(),
            'active_semester' => $activeSemester?->name,
            'requirements' => $activeSemester
                ? Requirement::where('semester_id', $activeSemester->id)->count()
                : 0,
            'members' => (clone $users)->where('status', 'approved')->count() - 1, // minus the admin
            'pending' => (clone $users)->where('status', 'pending')->count(),
            'chairs' => User::role('program_chair')->where('tenant_id', $tenant->id)->count(),
        ]);
    }
}
