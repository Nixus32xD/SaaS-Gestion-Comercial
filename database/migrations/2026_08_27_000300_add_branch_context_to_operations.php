<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
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
    ];

    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->unique(['id', 'business_id'], 'branches_id_business_id_unique');
        });

        foreach ($this->operationalTables as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->index(['business_id', 'branch_id']);
            });

            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->foreign(['branch_id', 'business_id'], "{$tableName}_branch_id_business_id_foreign")
                    ->references(['id', 'business_id'])
                    ->on('branches')
                    ->restrictOnDelete();
            });

            $this->backfillDefaultBranch($tableName);
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->operationalTables) as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropForeign("{$tableName}_branch_id_business_id_foreign");
                $table->dropIndex(['business_id', 'branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropUnique('branches_id_business_id_unique');
        });
    }

    private function backfillDefaultBranch(string $tableName): void
    {
        /** @var array<int, int> $defaultBranchIds */
        $defaultBranchIds = [];

        DB::table($tableName)
            ->whereNull('branch_id')
            ->orderBy('id')
            ->select(['id', 'business_id'])
            ->chunkById(500, function (Collection $records) use (&$defaultBranchIds, $tableName): void {
                foreach ($records as $record) {
                    $businessId = (int) $record->business_id;
                    $branchId = $defaultBranchIds[$businessId] ??= DB::table('branches')
                        ->where('business_id', $businessId)
                        ->where('is_default', true)
                        ->value('id');

                    if ($branchId === null) {
                        continue;
                    }

                    DB::table($tableName)
                        ->where('id', $record->id)
                        ->whereNull('branch_id')
                        ->update(['branch_id' => $branchId]);
                }
            }, 'id');
    }
};
