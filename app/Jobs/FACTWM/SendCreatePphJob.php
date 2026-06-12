<?php

namespace App\Jobs\FACTWM;

use App\Services\FACTWM\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCreatePphJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 360;

    public function __construct(
        public array $payload,
        public ?string $billingStatement = null
    ) {}

    public function handle(InvoiceService $invoiceService): void
    {
        $response = $invoiceService->sendCreatePphNow($this->payload);
        $billingStatement = data_get($response, 'data.BillingStatNo')
            ?? data_get($response, 'data.Billing_Stat_No')
            ?? $this->billingStatement
            ?? $this->payload['BillingStatNo']
            ?? null;

        if (! ($response['success'] ?? false)) {
            Log::error('SendCreatePphJob failed to submit PPH', [
                'billing_statement' => $billingStatement,
                'response' => $response,
            ]);
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('SendCreatePphJob crashed', [
            'billing_statement' => $this->billingStatement,
            'error' => $exception->getMessage(),
        ]);
    }
}
