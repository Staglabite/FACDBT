<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GoodReceiptNotes extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected $emailData;
    protected $file;

    public function __construct($emailData, $file = null)
    {
        $this->emailData = $emailData;
        $this->file = $file;
        $this->onQueue('emails');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "GR Dispute Notification - {$this->emailData['grNumber']}"
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'modules.FACTWM.FACTWM02.FACTWMF006.emails._good-receipt-notes-email',
            with: [
                'grNumber' => $this->emailData['grNumber'],
                'grNoteId' => $this->emailData['grNoteId'],
                'description' => $this->emailData['description'],
                'userName' => $this->emailData['userName'],
                'userEmail' => $this->emailData['userEmail'],
                'timestamp' => $this->emailData['timestamp']
            ]
        );
    }

    public function attachments(): array
    {
        $attachments = [];

        if ($this->file) {
            $attachments[] = $this->file;
        }

        return $attachments;
    }
}
