<?php

namespace App\Services;

use App\Models\ProductBatch;
use Illuminate\Support\Collection;

class ProductExpirationAlertService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForBusiness(int $businessId, int $limit = 10, ?int $daysThreshold = null, ?int $branchId = null): Collection
    {
        $today = now()->startOfDay();

        return $this->operationalBatchQuery($businessId, $branchId)
            ->with(['product:id,business_id,name,expiry_alert_days,is_active', 'branch:id,business_id,name,is_active'])
            ->whereNotNull('expires_at')
            ->where(function ($query) use ($today, $daysThreshold): void {
                $query->whereDate('expires_at', '<', $today->toDateString())
                    ->orWhere(function ($upcoming) use ($today, $daysThreshold): void {
                        if ($daysThreshold !== null) {
                            $upcoming
                                ->whereDate('expires_at', '>=', $today->toDateString())
                                ->whereDate('expires_at', '<=', $today->copy()->addDays($daysThreshold)->toDateString());

                            return;
                        }

                        $upcoming->withExpirationStatus('upcoming');
                    });
            })
            ->orderBy('expires_at')
            ->orderBy('product_batches.id')
            ->limit($limit)
            ->get(['product_batches.*'])
            ->map(function (ProductBatch $batch) use ($today): array {
                $daysRemaining = $batch->expires_at !== null
                    ? $today->diffInDays($batch->expires_at, false)
                    : null;
                $status = $batch->expirationStatus((int) ($batch->product?->expiry_alert_days ?? 15), $today);

                return [
                    'batch_id' => $batch->id,
                    'product_id' => $batch->product_id,
                    'product_name' => $batch->product?->name,
                    'branch_id' => $batch->branch_id,
                    'branch_name' => $batch->branch?->name,
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
        $batches = $this->operationalBatchQuery($businessId)
            ->whereNotNull('expires_at')
            ->get(['product_batches.expires_at']);

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

    private function operationalBatchQuery(int $businessId, ?int $branchId = null)
    {
        return ProductBatch::query()
            ->forBusiness($businessId)
            ->when($branchId !== null, fn ($query) => $query->where('product_batches.branch_id', $branchId))
            ->join('products', function ($join) use ($businessId): void {
                $join->on('products.id', '=', 'product_batches.product_id')
                    ->where('products.business_id', $businessId);
            })
            ->join('branches', function ($join) use ($businessId): void {
                $join->on('branches.id', '=', 'product_batches.branch_id')
                    ->where('branches.business_id', $businessId);
            })
            ->join('branch_product_stocks as branch_stock', function ($join) use ($businessId): void {
                $join->on('branch_stock.product_id', '=', 'product_batches.product_id')
                    ->on('branch_stock.branch_id', '=', 'product_batches.branch_id')
                    ->where('branch_stock.business_id', $businessId);
            })
            ->available()
            ->where('products.is_active', true)
            ->where('branches.is_active', true)
            ->whereRaw('branch_stock.stock - branch_stock.reserved_stock > 0');
    }
}
