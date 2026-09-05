<?php

namespace App\Jobs;

use App\Models\SaleFiscalDocument;
use App\Services\Fiscal\FiscalSaleDocumentService;
use DateTime;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ReconcileSaleFiscalDocumentJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public int $uniqueFor = 120;

    public function __construct(public int $saleFiscalDocumentId)
    {
        $this->afterCommit();
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return 'sale-fiscal-document:'.$this->saleFiscalDocumentId;
    }

    public function handle(FiscalSaleDocumentService $service): void
    {
        $document = $service->reconcileScheduled($this->saleFiscalDocumentId);

        if ($document === null) {
            return;
        }

        Log::info('fiscal_reconciliation_finished', [
            'business_id' => $document->business_id,
            'sale_id' => $document->sale_id,
            'sale_fiscal_document_id' => $document->id,
            'fiscal_document_id' => $document->fiscal_document_id,
            'attempt_number' => $document->attempt_number,
            'idempotency_key' => $document->fiscal_idempotency_key,
            'status' => $document->fiscal_status,
            'reconciliation_attempts' => $document->reconciliation_attempts,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('fiscal_reconciliation_job_failed', [
            'sale_fiscal_document_id' => $this->saleFiscalDocumentId,
            'error' => $exception->getMessage(),
        ]);
    }

    public function retryUntil(): DateTime
    {
        return now()->addMinutes(5);
    }
}
