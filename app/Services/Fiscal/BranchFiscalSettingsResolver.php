<?php

namespace App\Services\Fiscal;

use App\Models\BranchFiscalSetting;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Sale;
use LogicException;

class BranchFiscalSettingsResolver
{
    public function forSale(Sale $sale): BranchFiscalSetting|Business
    {
        $sale->loadMissing(['business', 'branch.fiscalSetting']);

        return $sale->branch?->fiscalSetting ?? $sale->business;
    }

    public function forBranch(Business $business, Branch $branch): BranchFiscalSetting|Business
    {
        if ((int) $branch->business_id !== (int) $business->id) {
            throw new LogicException('La sucursal no pertenece al comercio de la configuración ARCA.');
        }

        $setting = $branch->relationLoaded('fiscalSetting')
            ? $branch->fiscalSetting
            : $branch->fiscalSetting()->first();

        return $setting ?? $business;
    }

    public function isEnabledForBranch(Business $business, Branch $branch): bool
    {
        $setting = $this->forBranch($business, $branch);

        return $setting instanceof BranchFiscalSetting
            ? $setting->is_enabled
            : $business->hasElectronicBilling();
    }
}
