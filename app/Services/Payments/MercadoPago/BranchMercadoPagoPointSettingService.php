<?php

namespace App\Services\Payments\MercadoPago;

use App\Models\Branch;
use App\Models\BranchMercadoPagoPointSetting;
use App\Models\Business;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BranchMercadoPagoPointSettingService
{
    /** @param array<string, mixed> $payload */
    public function updateForBranch(Business $business, Branch $branch, array $payload, ?User $updatedBy = null): BranchMercadoPagoPointSetting
    {
        if ((int) $branch->business_id !== (int) $business->id) {
            throw ValidationException::withMessages([
                'branch_id' => 'La sucursal no pertenece al comercio actual.',
            ]);
        }

        return BranchMercadoPagoPointSetting::query()->updateOrCreate(
            ['business_id' => $business->id, 'branch_id' => $branch->id],
            [
                'is_enabled' => (bool) ($payload['is_enabled'] ?? false),
                'point_terminal_id' => $payload['point_terminal_id'] ?: null,
                'point_store_id' => $payload['point_store_id'] ?: null,
                'point_pos_id' => $payload['point_pos_id'] ?: null,
                'point_external_store_id' => $payload['point_external_store_id'] ?: null,
                'point_external_pos_id' => $payload['point_external_pos_id'] ?: null,
                'point_expiration_time' => $payload['point_expiration_time'] ?: null,
                'point_print_on_terminal' => $payload['point_print_on_terminal'] ?: null,
                'updated_by' => $updatedBy?->id,
            ]
        );
    }
}
