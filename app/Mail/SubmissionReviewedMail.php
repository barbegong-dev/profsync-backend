<?php

namespace App\Mail;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubmissionReviewedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $submission;
    public $reviewer;
    public $dashboardUrl;

    public function __construct(Submission $submission, ?User $reviewer = null)
    {
        $this->submission = $submission;
        $this->reviewer = $reviewer;
        $frontendUrl = config('app.frontend_url');
        $this->dashboardUrl = rtrim($frontendUrl, '/') . '/faculty/dashboard';
    }

    public function envelope(): Envelope
    {
        $statusText = ucfirst($this->submission->status);
        $reqName = $this->submission->requirement?->name ?? 'Document';

        return new Envelope(
            subject: "ProfSync: Submission {$statusText} - {$reqName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.submission_reviewed',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
