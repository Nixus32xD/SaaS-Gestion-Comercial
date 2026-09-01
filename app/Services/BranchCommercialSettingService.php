<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchCommercialSetting;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

class BranchCommercialSettingService
{
    public function forBranch(Branch $branch): BranchCommercialSetting
    {
        $branch->loadMissing('business');

        return BranchCommercialSetting::query()->firstOrCreate([
            'business_id' => $branch->business_id,
            'branch_id' => $branch->id,
        ], [
            'advanced_sale_settings_enabled' => $branch->business->hasAdvancedSaleSettings(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    public function update(Business $business, Branch $branch, array $payload): BranchCommercialSetting
    {
        $setting = $this->forBranch($branch);

        DB::transaction(function () use ($branch, $setting, $payload): void {
            $setting->update([
                'advanced_sale_settings_enabled' => (bool) $payload['advanced_sale_settings_enabled'],
            ]);

            foreach ((array) ($payload['sale_sectors'] ?? []) as $index => $sector) {
                $record = isset($sector['id'])
                    ? $branch->saleSectors()->findOrFail($sector['id'])
                    : $branch->saleSectors()->make();

                $record->fill([
                    'business_id' => $branch->business_id,
                    'name' => $sector['name'],
                    'description' => $sector['description'] ?: null,
                    'is_active' => (bool) $sector['is_active'],
                    'sort_order' => $index,
                ])->save();
            }

            foreach ((array) ($payload['payment_destinations'] ?? []) as $index => $destination) {
                $record = isset($destination['id'])
                    ? $branch->paymentDestinations()->findOrFail($destination['id'])
                    : $branch->paymentDestinations()->make();

                $record->fill([
                    'business_id' => $branch->business_id,
                    'name' => $destination['name'],
                    'account_holder' => $destination['account_holder'] ?: null,
                    'reference' => $destination['reference'] ?: null,
                    'account_number' => $destination['account_number'] ?: null,
                    'is_active' => (bool) $destination['is_active'],
                    'sort_order' => $index,
                ])->save();
            }
        });

        return $setting->refresh();
    }
}
