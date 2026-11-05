<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FACTWMF009Mail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $mailSubject,
        public string $mailMessage
    ) {
        $this->onQueue('emails');
    }

    public function build()
    {
        return $this->subject($this->mailSubject)
            ->view('modules.FACTWM.FACTWM02.FACTWMF009.partials._api-mail')
            ->with([
                'mailMessage' => $this->mailMessage,
            ]);
    }
}
