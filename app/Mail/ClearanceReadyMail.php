<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Semester;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClearanceReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $faculty;
    public $semester;
    public $totalCount;
    public $dashboardUrl;

    public function __construct(User $faculty, ?Semester $semester = null, int $totalCount = 0)
    {
        $this->faculty = $faculty;
        $this->semester = $semester;
        $this->totalCount = $totalCount;
        $frontendUrl = config('app.frontend_url');
        $this->dashboardUrl = rtrim($frontendUrl, '/') . '/faculty/dashboard';
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'ProfSync: Congratulations! You Are Ready for Clearance',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.clearance_ready',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
