<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private array $operationalTables = [
        'sales',
        'purchases',
        'stock_movements',
        'product_batches',
        'product_batch_movements',
        'product_batch_corrections',
    ];

    public function up(): void
    {
        $this->ensureEveryOperationalRecordHasABranch();

        foreach ($this->operationalTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('branch_id')->nullable(false)->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->operationalTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('branch_id')->nullable()->change();
            });
        }
    }

    private function ensureEveryOperationalRecordHasABranch(): void
    {
        $invalidTables = [];

        foreach ($this->operationalTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'branch_id')) {
                $invalidTables[] = "{$tableName} (sin columna branch_id)";

                continue;
            }

            if (DB::table($tableName)->whereNull('branch_id')->exists()) {
                $invalidTables[] = $tableName;
            }
        }

        if ($invalidTables !== []) {
            throw new \RuntimeException(
                'No es seguro volver obligatoria la sucursal. Ejecutá branches:audit-migration y corregí los registros pendientes: '
                .implode(', ', $invalidTables).'.'
            );
        }
    }
};
