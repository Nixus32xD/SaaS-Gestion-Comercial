<?php

namespace App\Services;

use App\Models\PurchaseItem;
use Illuminate\Support\Collection;

class ProductExpirationAlertService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listForBusiness(int $businessId, int $limit = 10): Collection
    {
        $today = now()->startOfDay();

        return PurchaseItem::query()
            ->forBusiness($businessId)
            ->whereNotNull('purchase_items.expires_at')
            ->orderBy('purchase_items.expires_at')
            ->orderBy('purchase_items.id')
            ->with(['product:id,name,expiry_alert_days,stock,is_active', 'purchase:id,purchase_number'])
            ->limit(250)
            ->get()
            ->filter(function (PurchaseItem $item) use ($today): bool {
                if ($item->product === null || ! $item->product->is_active || (float) $item->product->stock <= 0) {
                    return false;
                }

                $alertDays = (int) ($item->product?->expiry_alert_days ?? 15);
                $threshold = $today->copy()->addDays(max($alertDays, 1));

                return $item->expires_at !== null && $item->expires_at->startOfDay()->lessThanOrEqualTo($threshold);
            })
            ->take($limit)
            ->map(function (PurchaseItem $item) use ($today): array {
                $expiresAt = $item->expires_at?->copy()->startOfDay();
                $daysRemaining = $expiresAt ? $today->diffInDays($expiresAt, false) : null;
                $isExpired = $expiresAt?->isPast() ?? false;

                return [
                    'purchase_item_id' => $item->id,
                    'product_name' => $item->product_name,
                    'expires_at' => $item->expires_at?->toDateString(),
                    'purchase_number' => $item->purchase?->purchase_number,
                    'days_remaining' => $daysRemaining,
                    'status' => $isExpired ? 'expired' : 'upcoming',
                ];
            });
    }
}
