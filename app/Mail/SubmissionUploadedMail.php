<?php

namespace App\Mail;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubmissionUploadedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $submission;
    public $chair;
    public $reviewUrl;

    public function __construct(Submission $submission, ?User $chair = null)
    {
        $this->submission = $submission;
        $this->chair = $chair;
        $frontendUrl = config('app.frontend_url');
        $this->reviewUrl = rtrim($frontendUrl, '/') . '/chair/dashboard';
    }

    public function envelope(): Envelope
    {
        $reqName = $this->submission->requirement?->name ?? 'Document';
        $facultyName = $this->submission->faculty?->name ?? 'Faculty Member';

        return new Envelope(
            subject: "ProfSync: New Submission Uploaded - {$reqName} ({$facultyName})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.submission_uploaded',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
