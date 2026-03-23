<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Services\OperationalAlertNotificationService;
use Illuminate\Console\Command;

class SendOperationalAlertsCommand extends Command
{
    protected $signature = 'notifications:send-operational-alerts
        {--business=* : IDs de comercios a procesar}
        {--force : Reenvia aunque la alerta no haya cambiado}';

    protected $description = 'Procesa y envia alertas operativas por mail para cada comercio';

    public function __construct(private readonly OperationalAlertNotificationService $notificationService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $businessIds = collect((array) $this->option('business'))
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        $query = Business::query()
            ->when($businessIds !== [], fn ($builder) => $builder->whereKey($businessIds))
            ->when($businessIds === [], fn ($builder) => $builder->where('is_active', true))
            ->orderBy('id');

        $queued = 0;
        $sent = 0;
        $partial = 0;
        $failed = 0;
        $skipped = 0;

        $query->chunkById(50, function ($businesses) use (&$queued, &$sent, &$partial, &$failed, &$skipped): void {
            foreach ($businesses as $business) {
                $result = $this->notificationService->dispatchForBusiness(
                    $business,
                    (bool) $this->option('force'),
                );

                $label = "[{$business->id}] {$business->name}";

                match ($result['status']) {
                    'queued' => $this->info($label.' -> encolado'),
                    'sent' => $this->info($label.' -> enviado'),
                    'partial' => $this->warn($label.' -> envio parcial'),
                    'failed' => $this->error($label.' -> fallo el envio'),
                    default => $this->line($label.' -> omitido ('.($result['reason'] ?? 'sin motivo').')'),
                };

                match ($result['status']) {
                    'queued' => $queued++,
                    'sent' => $sent++,
                    'partial' => $partial++,
                    'failed' => $failed++,
                    default => $skipped++,
                };
            }
        });

        $this->newLine();
        $this->line("Resumen -> encolados: {$queued}, enviados: {$sent}, parciales: {$partial}, fallidos: {$failed}, omitidos: {$skipped}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
