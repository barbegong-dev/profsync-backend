<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Requirement;
use App\Models\Semester;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Submission analytics for the active semester.
 * Admins see the whole workspace; program chairs see their own department.
 */
class AnalyticsController extends Controller
{
    private const ACTIVITY_WEEKS = 8;
    private const ATTENTION_LIMIT = 5;

    public function index()
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('admin');
        $departmentId = $isAdmin ? null : $user->department_id;
        abort_if(!$isAdmin && !$departmentId, 403, 'You are not assigned to a department.');

        $semester = Semester::where('is_active', true)->first();
        $requirements = $semester
            ? Requirement::where('semester_id', $semester->id)->orderByRaw('deadline IS NULL, deadline')->orderBy('name')->get()
            : collect();
        $requiredIds = $requirements->where('is_required', true)->pluck('id');

        $members = User::with(['roles', 'department'])
            ->where('tenant_id', $user->tenant_id)
            ->when($departmentId, fn ($q) => $q->where('department_id', $departmentId))
            ->get();
        $faculty = $members->filter(fn ($m) => $m->status === 'approved' && $m->hasRole('faculty'))->values();

        $submissions = Submission::whereIn('faculty_id', $faculty->pluck('id'))
            ->whereIn('requirement_id', $requirements->pluck('id'))
            ->get();
        $byFaculty = $submissions->groupBy('faculty_id');

        $now = Carbon::now();

        // One row per faculty member: their status counts and clearance
        $facultyStats = $faculty->map(function ($f) use ($byFaculty, $requirements, $requiredIds, $now) {
            $subs = ($byFaculty[$f->id] ?? collect())->keyBy('requirement_id');
            $counts = $this->statusCounts($subs, $requirements->count());
            $overdue = $requirements->filter(fn ($r) => !$subs->has($r->id) && $r->deadline && Carbon::parse($r->deadline)->lt($now))->count();
            $approvedRequired = $subs->whereIn('requirement_id', $requiredIds)->where('status', 'approved')->count();

            return [
                'id' => $f->id,
                'name' => $f->name,
                'department_id' => $f->department_id,
                'department' => $f->department?->name,
                ...$counts,
                'overdue' => $overdue,
                'ready' => $requiredIds->count() > 0 && $approvedRequired >= $requiredIds->count(),
            ];
        });

        $summary = [
            'faculty' => $faculty->count(),
            'requirements' => $requirements->count(),
            ...$this->sumCounts($facultyStats),
            'overdue' => $facultyStats->sum('overdue'),
            'ready_for_clearance' => $facultyStats->where('ready', true)->count(),
            'chairs' => $members->filter(fn ($m) => $m->hasRole('program_chair'))->count(),
        ];

        if ($isAdmin) {
            $summary['pending_signups'] = User::where('tenant_id', $user->tenant_id)->where('status', 'pending')->count();
        }

        $byRequirement = $requirements->map(function ($r) use ($submissions, $faculty, $now) {
            $subs = $submissions->where('requirement_id', $r->id);
            $counts = $this->statusCounts($subs, $faculty->count());
            $pastDeadline = $r->deadline && Carbon::parse($r->deadline)->lt($now);

            return [
                'id' => $r->id,
                'name' => $r->name,
                'deadline' => $r->deadline,
                'is_required' => (bool) $r->is_required,
                ...$counts,
                'overdue' => $pastDeadline ? $counts['missing'] : 0,
            ];
        })->values();

        return response()->json([
            'scope' => $isAdmin ? 'workspace' : 'department',
            'department' => $departmentId ? $user->department?->only('id', 'name') : null,
            'semester' => $semester?->only('id', 'name'),
            'max_chairs' => MemberController::MAX_CHAIRS,
            'summary' => $summary,
            'by_department' => $isAdmin ? $this->byDepartment($facultyStats, $members) : [],
            'by_requirement' => $byRequirement,
            'activity' => $this->weeklyActivity($submissions, $now),
            'attention' => $facultyStats
                ->filter(fn ($f) => !$f['ready'] && ($f['missing'] + $f['rejected']) > 0)
                ->sortByDesc(fn ($f) => [$f['overdue'], $f['missing'] + $f['rejected']])
                ->take(self::ATTENTION_LIMIT)
                ->values(),
            'roles' => $isAdmin ? $this->roleCounts($members) : null,
        ]);
    }

    /** Approved / pending / rejected / missing out of the expected number of submissions. */
    private function statusCounts(Collection $subs, int $expected): array
    {
        $approved = $subs->where('status', 'approved')->count();
        $pending = $subs->where('status', 'pending')->count();
        $rejected = $subs->where('status', 'rejected')->count();

        return [
            'expected' => $expected,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'missing' => max(0, $expected - $approved - $pending - $rejected),
            'completion_rate' => $expected > 0 ? round($approved / $expected * 100, 1) : 0,
        ];
    }

    private function sumCounts(Collection $rows): array
    {
        $expected = $rows->sum('expected');
        $approved = $rows->sum('approved');

        return [
            'expected' => $expected,
            'approved' => $approved,
            'pending' => $rows->sum('pending'),
            'rejected' => $rows->sum('rejected'),
            'missing' => $rows->sum('missing'),
            'completion_rate' => $expected > 0 ? round($approved / $expected * 100, 1) : 0,
        ];
    }

    private function byDepartment(Collection $facultyStats, Collection $members): array
    {
        return Department::orderBy('name')->get()->map(function ($d) use ($facultyStats, $members) {
            $rows = $facultyStats->where('department_id', $d->id);

            return [
                'id' => $d->id,
                'name' => $d->name,
                'faculty' => $rows->count(),
                'chairs' => $members->filter(fn ($m) => $m->department_id === $d->id && $m->hasRole('program_chair'))->count(),
                ...$this->sumCounts($rows),
                'ready' => $rows->where('ready', true)->count(),
            ];
        })->all();
    }

    /** Submissions first uploaded in each of the last ACTIVITY_WEEKS weeks. */
    private function weeklyActivity(Collection $submissions, Carbon $now): array
    {
        $weeks = [];
        $start = $now->copy()->startOfWeek()->subWeeks(self::ACTIVITY_WEEKS - 1);

        for ($i = 0; $i < self::ACTIVITY_WEEKS; $i++) {
            $from = $start->copy()->addWeeks($i);
            $to = $from->copy()->addWeek();
            $weeks[] = [
                'week_start' => $from->toDateString(),
                'uploads' => $submissions->filter(fn ($s) => $s->created_at >= $from && $s->created_at < $to)->count(),
            ];
        }

        return $weeks;
    }

    private function roleCounts(Collection $members): array
    {
        $approved = $members->where('status', 'approved');

        return collect(['faculty', 'program_chair', 'dean', 'admin'])
            ->mapWithKeys(fn ($role) => [$role => $approved->filter(fn ($m) => $m->hasRole($role))->count()])
            ->all();
    }
}
