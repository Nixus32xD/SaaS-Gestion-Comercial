<?php

use App\Support\FiscalPointOfSale;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        $businessIds = DB::table('businesses')
            ->whereNotNull('fiscal_point_of_sale')
            ->where('fiscal_point_of_sale', '>', FiscalPointOfSale::MAX)
            ->pluck('id');
        $branchIds = DB::table('branch_fiscal_settings')
            ->whereNotNull('fiscal_point_of_sale')
            ->where('fiscal_point_of_sale', '>', FiscalPointOfSale::MAX)
            ->pluck('id');

        if ($businessIds->isEmpty() && $branchIds->isEmpty()) {
            return;
        }

        Log::critical('Fiscal point of sale range preflight failed.', [
            'business_ids' => $businessIds->all(),
            'branch_fiscal_setting_ids' => $branchIds->all(),
            'maximum' => FiscalPointOfSale::MAX,
        ]);

        throw new RuntimeException(
            'No se aplicó la validación de punto de venta: hay configuraciones fiscales fuera del rango WSFEv1 (1 a '.FiscalPointOfSale::MAX.'). Corrige los IDs informados en el log antes de reintentar.'
        );
    }

    public function down(): void
    {
        // This migration only validates existing data; it does not mutate it.
    }
};
