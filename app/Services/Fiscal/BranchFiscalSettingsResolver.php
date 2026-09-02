<?php

namespace App\Services\Fiscal;

use App\Models\Branch;
use App\Models\BranchFiscalSetting;
use App\Models\Business;
use App\Models\FiscalIdentity;
use App\Models\Sale;
use Illuminate\Validation\ValidationException;
use LogicException;

class BranchFiscalSettingsResolver
{
    public function forSale(Sale $sale): BranchFiscalSetting|Business
    {
        $sale->loadMissing(['business', 'branch.fiscalSetting.fiscalIdentity']);

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
            && $setting->is_enabled
            && $setting->fiscal_identity_id !== null;
    }

    public function identityForSale(Sale $sale): FiscalIdentity
    {
        $sale->loadMissing(['branch.fiscalSetting.fiscalIdentity']);
        $setting = $sale->branch?->fiscalSetting;

        if (! $setting?->is_enabled || $setting->fiscalIdentity === null) {
            throw ValidationException::withMessages([
                'fiscal' => 'La sucursal no tiene una identidad fiscal explícita habilitada.',
            ]);
        }

        return $setting->fiscalIdentity;
    }
}
