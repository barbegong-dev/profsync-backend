<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One email layout for every account event (sign-up, approval, role change, removal, workspace changes).
 * Built from plain values so it can still be sent after the user record is deleted.
 * Use App\Support\AccountEmails to create these.
 */
class AccountNotificationMail extends Mailable
{
    use Queueable;

    /**
     * @param string[]              $paragraphs Body paragraphs (trusted HTML, escape user input before passing)
     * @param array<string, string> $details    Label => value rows shown in a box
     * @param string                $tone       success | warning | danger | info
     */
    public function __construct(
        public string $subjectLine,
        public string $status,
        public string $recipientName,
        public array $paragraphs,
        public array $details = [],
        public ?string $actionText = null,
        public ?string $actionUrl = null,
        public string $tone = 'info',
        public ?string $workspaceName = null,
        public ?string $footnote = null,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ProfSync: ' . $this->subjectLine);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account_notification');
    }
}
