<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Business;
use LogicException;

class BranchCommercialSettingsResolver
{
    public function advancedSaleSettingsEnabled(Business $business, Branch $branch): bool
    {
        if ((int) $branch->business_id !== (int) $business->id) {
            throw new LogicException('La sucursal no pertenece al comercio de la configuración comercial.');
        }

        $setting = $branch->relationLoaded('commercialSetting')
            ? $branch->commercialSetting
            : $branch->commercialSetting()->first();

        return $setting === null
            ? $business->hasAdvancedSaleSettings()
            : $setting->advanced_sale_settings_enabled;
    }
}
