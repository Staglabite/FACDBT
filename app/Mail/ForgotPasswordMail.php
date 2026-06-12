<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForgotPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $token;

    /**
     * Create a new message instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
        $this->onQueue('emails');
    }

    public function build()
    {
        return $this->subject("Reset Password")
            ->view('modules.HITUAM.HITUAM02.HITUAMF009.partials._mail-forgot-password')
            ->with(['token' => $this->token]);
    }
}
