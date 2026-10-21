<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function reports(Request $request)
    {
        $query = Submission::select(
            'users.department_id',
            DB::raw('count(*) as total'),
            DB::raw("sum(case when status='pending' then 1 else 0 end) as pending"),
            DB::raw("sum(case when status='approved' then 1 else 0 end) as approved"),
            DB::raw("sum(case when status='rejected' then 1 else 0 end) as rejected")
        )
            ->join('users', 'users.id', '=', 'submissions.faculty_id');

        // Allow filtering by department (for Deans who want to see specific dept stats)
        if ($request->has('department_id') && $request->department_id) {
            $query->where('users.department_id', $request->department_id);
        }

        return $query->groupBy('users.department_id')
            ->get();
    }

    public function compliance()
    {
        // Compliance report: List of faculty, their submission status, and clearance readiness
        $user = auth()->user();
        
        $activeSemester = \App\Models\Semester::where('is_active', true)->first();
        $reqQuery = \App\Models\Requirement::where('is_required', true);
        if ($activeSemester) {
            $reqQuery->where('semester_id', $activeSemester->id);
        }
        $requirements = $reqQuery->get();
        $totalReqs = $requirements->count();

        // Get all faculty in the department
        $faculty = \App\Models\User::role('faculty')
            ->where('department_id', $user->department_id)
            ->with(['submissions.requirement'])
            ->get();

        return response()->json([
            'faculty' => $faculty->map(function($f) use ($requirements, $totalReqs) {
                $submissionsList = $requirements->map(function($r) use ($f) {
                    $sub = $f->submissions->where('requirement_id', $r->id)->first();
                    return [
                        'requirement_id' => $r->id,
                        'requirement_name' => $r->name,
                        'status' => $sub ? $sub->status : 'missing',
                        'deadline' => $r->deadline,
                    ];
                });

                $approvedCount = $submissionsList->where('status', 'approved')->count();
                $isReady = ($totalReqs > 0 && $approvedCount >= $totalReqs);

                return [
                    'id' => $f->id,
                    'name' => $f->name,
                    'email' => $f->email,
                    'submissions' => $submissionsList,
                    'approved_count' => $approvedCount,
                    'total_count' => $totalReqs,
                    'is_ready_for_clearance' => $isReady
                ];
            }),
            'requirements' => $requirements,
            'semester' => $activeSemester
        ]);
    }
}
