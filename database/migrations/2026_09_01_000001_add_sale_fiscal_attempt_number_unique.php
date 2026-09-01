<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('sale_fiscal_documents')
            ->select('business_id', 'sale_id', 'attempt_number')
            ->groupBy('business_id', 'sale_id', 'attempt_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException('Hay intentos fiscales duplicados; corregilos antes de activar la restricción de concurrencia.');
        }

        Schema::table('sale_fiscal_documents', function (Blueprint $table): void {
            $table->unique(['business_id', 'sale_id', 'attempt_number'], 'sale_fiscal_attempt_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('sale_fiscal_documents', function (Blueprint $table): void {
            $table->dropUnique('sale_fiscal_attempt_number_unique');
        });
    }
};
