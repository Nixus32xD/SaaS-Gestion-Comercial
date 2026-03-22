<?php

namespace App\Services;

use App\Models\ProductBatch;
use Illuminate\Support\Collection;

class ProductExpirationAlertService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForBusiness(int $businessId, int $limit = 10, ?int $daysThreshold = null): Collection
    {
        $today = now()->startOfDay();
        $maxDays = $daysThreshold ?? 30;

        return ProductBatch::query()
            ->forBusiness($businessId)
            ->with(['product:id,business_id,name,expiry_alert_days,is_active,stock'])
            ->available()
            ->whereNotNull('expires_at')
            ->whereHas('product', fn ($query) => $query->where('is_active', true)->where('stock', '>', 0))
            ->where(function ($query) use ($today, $maxDays): void {
                $query
                    ->where('expires_at', '<', $today->toDateString())
                    ->orWhere('expires_at', '<=', $today->copy()->addDays($maxDays)->toDateString());
            })
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->filter(function (ProductBatch $batch) use ($today): bool {
                $product = $batch->product;

                if ($product === null || $batch->expires_at === null) {
                    return false;
                }

                $alertDays = max((int) ($product->expiry_alert_days ?? 15), 1);

                return $batch->expires_at->lte($today->copy()->addDays($alertDays));
            })
            ->values()
            ->map(function (ProductBatch $batch) use ($today): array {
                $daysRemaining = $batch->expires_at !== null
                    ? $today->diffInDays($batch->expires_at, false)
                    : null;
                $status = $batch->expirationStatus((int) ($batch->product?->expiry_alert_days ?? 15), $today);

                return [
                    'batch_id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'product_name' => $batch->product?->name,
                    'batch_code' => $batch->batch_code,
                    'expires_at' => $batch->expires_at?->toDateString(),
                    'quantity' => (float) $batch->quantity,
                    'days_remaining' => $daysRemaining,
                    'status' => $status === 'valid' ? 'upcoming' : $status,
                ];
            });
    }

    /**
     * @param  list<int>  $thresholds
     * @return array<string, int>
     */
    public function summarizeForBusiness(int $businessId, array $thresholds = [7, 15, 30]): array
    {
        $today = now()->startOfDay();
        $batches = ProductBatch::query()
            ->forBusiness($businessId)
            ->available()
            ->whereNotNull('expires_at')
            ->get(['expires_at']);

        $summary = [
            'expired' => 0,
        ];

        foreach ($thresholds as $threshold) {
            $summary['within_'.$threshold.'_days'] = 0;
        }

        foreach ($batches as $batch) {
            if ($batch->expires_at === null) {
                continue;
            }

            if ($batch->expires_at->lt($today)) {
                $summary['expired']++;

                continue;
            }

            foreach ($thresholds as $threshold) {
                if ($batch->expires_at->lte($today->copy()->addDays((int) $threshold))) {
                    $summary['within_'.$threshold.'_days']++;
                }
            }
        }

        return $summary;
    }
}
