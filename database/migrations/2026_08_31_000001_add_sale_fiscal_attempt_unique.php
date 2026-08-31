<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicates = DB::table('sale_fiscal_documents')
            ->select(['sale_id', 'attempt_number'])
            ->groupBy('sale_id', 'attempt_number')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                'No se puede agregar sale_fiscal_documents_sale_attempt_unique: existen intentos fiscales duplicados. Audita y concilia esos registros antes de desplegar esta migracion.'
            );
        }

        if (! Schema::hasIndex('sale_fiscal_documents', 'sale_fiscal_documents_sale_attempt_unique')) {
            Schema::table('sale_fiscal_documents', function (Blueprint $table): void {
                $table->unique(['sale_id', 'attempt_number'], 'sale_fiscal_documents_sale_attempt_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('sale_fiscal_documents', 'sale_fiscal_documents_sale_attempt_unique')) {
            Schema::table('sale_fiscal_documents', function (Blueprint $table): void {
                $table->dropUnique('sale_fiscal_documents_sale_attempt_unique');
            });
        }
    }
};
