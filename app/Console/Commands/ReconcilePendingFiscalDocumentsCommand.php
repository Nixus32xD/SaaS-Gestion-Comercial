<?php

namespace App\Console\Commands;

use App\Jobs\ReconcileSaleFiscalDocumentJob;
use App\Models\SaleFiscalDocument;
use Illuminate\Console\Command;

class ReconcilePendingFiscalDocumentsCommand extends Command
{
    protected $signature = 'fiscal:reconcile-pending {--limit= : Máximo de documentos a encolar}';

    protected $description = 'Encola conciliaciones seguras de comprobantes fiscales inciertos o estancados';

    public function handle(): int
    {
        $limit = max(1, min((int) ($this->option('limit') ?: config('fiscal.reconciliation.scan_limit', 100)), 500));
        $staleAt = now()->subMinutes(max(1, (int) config('fiscal.reconciliation.stale_minutes', 5)));
        $maxAttempts = max(1, (int) config('fiscal.reconciliation.max_attempts', 5));

        $documents = SaleFiscalDocument::query()
            ->whereIn('fiscal_status', [
                SaleFiscalDocument::STATUS_UNCERTAIN,
                SaleFiscalDocument::STATUS_PROCESSING,
            ])
            ->where('reconciliation_attempts', '<', $maxAttempts)
            ->where(function ($query) use ($staleAt): void {
                $query->where('reconciliation_next_attempt_at', '<=', now())
                    ->orWhere(function ($query) use ($staleAt): void {
                        $query->whereNull('reconciliation_next_attempt_at')
                            ->where('updated_at', '<=', $staleAt);
                    });
            })
            ->orderBy('reconciliation_next_attempt_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($documents as $documentId) {
            ReconcileSaleFiscalDocumentJob::dispatch((int) $documentId);
        }

        $this->info("Conciliaciones fiscales encoladas: {$documents->count()}");

        return self::SUCCESS;
    }
}
