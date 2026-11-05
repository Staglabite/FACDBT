<?php

namespace App\Jobs\FACTWM;

use App\Models\FACTWM02\FACTWM_TRHGR_NOTES as GRNote;
use App\Models\FACTWM02\FACTWM_TRHVERIFY_NON_PO as VerifyNonPo;
use App\Models\FACTWM02\FACTWM_TRHVERIFY_PO as VerifyPo;
use App\Services\FACTWM\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 360;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $payload,
        public ?string $billingStatement = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(InvoiceService $invoiceService): void
    {
        $response = $invoiceService->sendInvoiceNow($this->payload);
        $billingStatement = data_get($response, 'Billing_Statement_No')
            ?? data_get($response, 'billing_statement')
            ?? data_get($response, 'data.Billing_Statement_No')
            ?? data_get($response, 'data.Billing_Stat_No')
            ?? $this->billingStatement
            ?? $invoiceService->extractBillingStatement($this->payload);

        if (! $billingStatement) {
            Log::warning('SendInvoiceJob could not resolve billing statement', [
                'payload' => $this->payload,
                'response' => $response,
            ]);

            return;
        }

        if (! ($response['success'] ?? false)) {
            Log::error('SendInvoiceJob failed to submit invoice', [
                'billing_statement' => $billingStatement,
                'response' => $response,
            ]);

            $this->updateLocalStatus($billingStatement, 'FAILED');

            return;
        }

        $status = data_get($response, 'data.status', 'WAITING');
        $planPayDate = $invoiceService->extractPlanPayDate($response);

        $this->updateLocalStatus($billingStatement, $status, $planPayDate);
    }

    public function failed(Throwable $exception): void
    {
        if (! $this->billingStatement) {
            return;
        }

        Log::critical('SendInvoiceJob crashed', [
            'billing_statement' => $this->billingStatement,
            'error' => $exception->getMessage(),
        ]);

        $this->updateLocalStatus($this->billingStatement, 'FAILED');
    }

    private function updateLocalStatus(string $billingStatement, string $status, ?string $planPayDate = null): void
    {
        $isNonPo = str_contains(strtoupper($billingStatement), 'NP');
        $updates = [
            'VSTATUS_INVOICE' => $status,
        ];

        if ($planPayDate) {
            $updates['DPAYMENT_DATE'] = $planPayDate;
        }

        if ($isNonPo) {
            VerifyNonPo::query()
                ->where('VBILLING_STATEMENT', $billingStatement)
                ->where('VSTATUS', 'submit')
                ->update($updates);

            return;
        }

        $verifyPo = VerifyPo::query()
            ->where('VBILLING_STATEMENT', $billingStatement)
            ->where('VSTATUS', 'submit')
            ->first();

        if (! $verifyPo) {
            return;
        }

        VerifyPo::query()
            ->where('IID', $verifyPo->IID)
            ->update($updates);

        $grIds = collect($verifyPo->VGR_NUMBER_IID)
            ->filter(fn($id) => ! is_null($id) && $id !== '')
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->toArray();

        if (! empty($grIds)) {
            GRNote::query()
                ->whereIn('IID', $grIds)
                ->update([
                    'VSTATUS_SUBMITTED' => $status,
                ]);
        }
    }
}
