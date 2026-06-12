<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendNotificationScanVerifyMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $billingData;

    /**
     * Create a new message instance.
     */
    public function __construct($billingData)
    {
        $this->billingData = $billingData;
        $this->onQueue('emails');
    }

    public function build()
    {
        return $this->subject("Billing Statement")
            ->view('modules.FACTWM.FACTWM02.FACTWMF009.partials._mail-for-notification')
            ->with([
                'nomorBilling' => $this->billingData['nomor_billing'],
                'tanggal' => $this->billingData['tanggal'],
                'jam' => $this->billingData['jam'],
                'status' => $this->billingData['status'],
            ]);
    }
}
