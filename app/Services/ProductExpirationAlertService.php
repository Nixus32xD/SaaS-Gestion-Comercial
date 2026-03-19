<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProductExpirationAlertService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForBusiness(int $businessId, int $limit = 10): Collection
    {
        $today = now()->startOfDay();

        return DB::table('purchase_items')
            ->join('products', function ($join): void {
                $join->on('products.id', '=', 'purchase_items.product_id')
                    ->on('products.business_id', '=', 'purchase_items.business_id');
            })
            ->leftJoin('purchases', function ($join): void {
                $join->on('purchases.id', '=', 'purchase_items.purchase_id')
                    ->on('purchases.business_id', '=', 'purchase_items.business_id');
            })
            ->where('purchase_items.business_id', $businessId)
            ->whereNotNull('purchase_items.expires_at')
            ->where('products.is_active', true)
            ->where('products.stock', '>', 0)
            ->whereRaw(
                'purchase_items.expires_at <= DATE_ADD(?, INTERVAL GREATEST(COALESCE(products.expiry_alert_days, 15), 1) DAY)',
                [$today->toDateString()]
            )
            ->orderBy('purchase_items.expires_at')
            ->orderBy('purchase_items.id')
            ->limit($limit)
            ->select([
                'purchase_items.id as purchase_item_id',
                'purchase_items.product_name',
                'purchase_items.expires_at',
                'purchases.purchase_number',
            ])
            ->get()
            ->map(function (object $item) use ($today): array {
                $expiresAt = $item->expires_at !== null ? now()->parse($item->expires_at)->startOfDay() : null;
                $daysRemaining = $expiresAt ? $today->diffInDays($expiresAt, false) : null;
                $isExpired = $expiresAt?->isPast() ?? false;

                return [
                    'purchase_item_id' => $item->purchase_item_id,
                    'product_name' => $item->product_name,
                    'expires_at' => $expiresAt?->toDateString(),
                    'purchase_number' => $item->purchase_number,
                    'days_remaining' => $daysRemaining,
                    'status' => $isExpired ? 'expired' : 'upcoming',
                ];
            });
    }
}
