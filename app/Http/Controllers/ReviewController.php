<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\Notification;
use App\Mail\SubmissionReviewedMail;
use App\Mail\ClearanceReadyMail;
use App\Models\Semester;
use App\Models\Requirement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    public function index(Request $request)
    {
        // For Program Chair to see all submissions in their department
        $user = auth()->user();
        $query = Submission::with(['faculty', 'requirement'])
            ->whereHas('faculty', function($q) use ($user) {
                $q->where('department_id', $user->department_id);
            });

        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->has('requirement_id')) {
            $query->where('requirement_id', $request->requirement_id);
        }

        return $query->get();
    }

    public function review(Request $request, Submission $submission)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'remarks' => 'nullable|string'
        ]);

        $submission->update([
            'status' => $request->status,
            'remarks' => $request->remarks
        ]);

        $submission->load(['faculty.department', 'requirement']);

        $reqName = $submission->requirement?->name ?? "Requirement #{$submission->requirement_id}";
        $chair = auth()->user();
        $chairName = $chair?->name ?? 'Program Chair';

        // In-app Notification for review
        try {
            Notification::create([
                'user_id' => $submission->faculty_id,
                'message' => "Your submission for '{$reqName}' has been {$request->status} by {$chairName}." . ($request->remarks ? " Remarks: {$request->remarks}" : '')
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create in-app review notification: ' . $e->getMessage());
        }

        // Email Notification to Faculty for review result
        if ($submission->faculty && $submission->faculty->email) {
            try {
                Log::info("Sending review email to faculty: {$submission->faculty->email} for submission #{$submission->id} ({$request->status})");
                Mail::to($submission->faculty->email)->send(new SubmissionReviewedMail($submission, $chair));
                Log::info("Review email sent successfully to {$submission->faculty->email}");
            } catch (\Throwable $e) {
                Log::error('Failed to send submission reviewed email: ' . $e->getMessage());
            }
        } else {
            Log::warning("Cannot send review email: Faculty email not found for submission #{$submission->id}");
        }

        // Check if Faculty has now completed ALL requirements for clearance
        $clearanceReached = false;
        if ($request->status === 'approved' && $submission->faculty) {
            try {
                $activeSemester = Semester::where('is_active', true)->first();
                $reqQuery = Requirement::where('is_required', true)->forDepartment($submission->faculty->department_id);
                if ($activeSemester) {
                    $reqQuery->where('semester_id', $activeSemester->id);
                }
                $requiredIds = $reqQuery->pluck('id');
                $totalRequired = $requiredIds->count();

                if ($totalRequired > 0) {
                    $approvedCount = Submission::where('faculty_id', $submission->faculty_id)
                        ->whereIn('requirement_id', $requiredIds)
                        ->where('status', 'approved')
                        ->count();

                    if ($approvedCount >= $totalRequired) {
                        $clearanceReached = true;

                        // Create in-app Clearance notification for faculty
                        Notification::create([
                            'user_id' => $submission->faculty_id,
                            'message' => "Congratulations! All your requirements have been approved. You are officially tagged as Ready for Clearance."
                        ]);

                        // Send Clearance Ready Email to Faculty
                        if (!empty($submission->faculty->email)) {
                            Log::info("Sending Clearance Ready email to faculty: {$submission->faculty->email}");
                            Mail::to($submission->faculty->email)->send(new ClearanceReadyMail($submission->faculty, $activeSemester, $totalRequired));
                            Log::info("Clearance Ready email sent successfully to {$submission->faculty->email}");
                        }

                        // Also notify Program Chair
                        if ($chair) {
                            Notification::create([
                                'user_id' => $chair->id,
                                'message' => "Faculty member {$submission->faculty->name} has completed all requirements and is now Ready for Clearance."
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Error evaluating clearance readiness: ' . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'submission' => $submission,
            'clearance_ready' => $clearanceReached,
            'message' => "Submission has been {$request->status} and the faculty member has been notified by email." . ($clearanceReached ? " Faculty is now Ready for Clearance!" : "")
        ]);
    }
}
