<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Models\Submission;
use App\Models\Notification;
use App\Models\User;
use App\Mail\SubmissionUploadedMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SubmissionController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'requirement_id' => 'required|exists:requirements,id',
            'document' => 'required|file|mimes:pdf,docx,doc'
        ]);

        $path = $request->file('document')->store('submissions', 'public');

        $submission = Submission::updateOrCreate(
            [
                'faculty_id' => Auth::id(),
                'requirement_id' => $request->requirement_id,
            ],
            [
                'file_path' => $path,
                'status' => 'pending',
                'remarks' => null // Clear remarks on re-upload
            ]
        );

        $submission->load(['faculty.department', 'requirement']);
        $reqName = $submission->requirement?->name ?? "Requirement #{$request->requirement_id}";
        $faculty = $submission->faculty;

        // In-app notification for faculty
        try {
            Notification::create([
                'user_id' => Auth::id(),
                'message' => "Your submission for '{$reqName}' has been uploaded and is pending review."
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to create in-app upload notification: ' . $e->getMessage());
        }

        // Notify Program Chairs of the faculty's department (in-app + email)
        $chairs = User::role('program_chair')
            ->where('department_id', $faculty?->department_id)
            ->get();

        foreach ($chairs as $chair) {
            try {
                Notification::create([
                    'user_id' => $chair->id,
                    'message' => "{$faculty->name} uploaded a submission for '{$reqName}' that needs your review."
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to create in-app chair notification: ' . $e->getMessage());
            }

            if (empty($chair->email)) {
                continue;
            }

            try {
                Log::info("Sending upload notification email to Program Chair: {$chair->email} for submission #{$submission->id}");
                Mail::to($chair->email)->send(new SubmissionUploadedMail($submission, $chair));
                Log::info("Upload notification email sent successfully to {$chair->email}");
            } catch (\Throwable $e) {
                Log::error('Failed to send submission uploaded email: ' . $e->getMessage());
            }
        }

        return response()->json(['success' => true, 'submission' => $submission]);
    }

    public function checklist()
    {
        $activeSemester = \App\Models\Semester::where('is_active', true)->first();

        if (!$activeSemester) {
            return response()->json([]);
        }

        $requirements = Requirement::where('semester_id', $activeSemester->id)->get();
        $submissions = Submission::where('faculty_id', Auth::id())
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

    public function download(Submission $submission)
    {
        // Faculty may only download their own files; admin, chair and dean may download any
        Gate::authorize('view', $submission);

        if (!Storage::disk('public')->exists($submission->file_path)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return Storage::disk('public')->download($submission->file_path);
    }
}
