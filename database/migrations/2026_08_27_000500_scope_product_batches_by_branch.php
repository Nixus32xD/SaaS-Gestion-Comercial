<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<int, int> */
    private array $defaultBranches = [];

    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable()->after('business_id');
        });

        Schema::table('product_batch_movements', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable()->after('business_id');
        });

        Schema::table('product_batch_corrections', function (Blueprint $table): void {
            $table->unsignedBigInteger('branch_id')->nullable()->after('business_id');
        });

        $this->backfillBatches();
        $this->backfillRecordsFromBatch('product_batch_movements');
        $this->backfillRecordsFromBatch('product_batch_corrections');

        Schema::table('product_batches', function (Blueprint $table): void {
            $table->dropUnique('product_batches_business_product_batch_code_unique');
            $table->unique(
                ['business_id', 'branch_id', 'product_id', 'batch_code'],
                'product_batches_business_branch_product_code_unique'
            );
            $table->unique(['id', 'business_id', 'branch_id'], 'product_batches_id_business_branch_unique');
            $table->index(
                ['business_id', 'branch_id', 'product_id', 'expires_at'],
                'product_batches_business_branch_product_expires_index'
            );
            $table->index(
                ['business_id', 'branch_id', 'product_id', 'quantity'],
                'product_batches_business_branch_product_quantity_index'
            );
            $table->index(['business_id', 'branch_id', 'expires_at'], 'product_batches_business_branch_expires_index');

            $table->foreign(['branch_id', 'business_id'], 'product_batches_branch_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->restrictOnDelete();
        });

        Schema::table('product_batch_movements', function (Blueprint $table): void {
            $table->index(
                ['business_id', 'branch_id', 'product_id', 'created_at'],
                'product_batch_movements_business_branch_product_created_index'
            );
            $table->index(
                ['business_id', 'branch_id', 'product_batch_id', 'created_at'],
                'product_batch_movements_business_branch_batch_created_index'
            );

            $table->foreign(['branch_id', 'business_id'], 'product_batch_movements_branch_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(
                ['product_batch_id', 'business_id', 'branch_id'],
                'product_batch_movements_batch_business_branch_foreign'
            )
                ->references(['id', 'business_id', 'branch_id'])
                ->on('product_batches')
                ->cascadeOnDelete();
        });

        Schema::table('product_batch_corrections', function (Blueprint $table): void {
            $table->index(
                ['business_id', 'branch_id', 'product_id', 'created_at'],
                'product_batch_corrections_business_branch_product_created_index'
            );
            $table->index(
                ['business_id', 'branch_id', 'product_batch_id', 'created_at'],
                'product_batch_corrections_business_branch_batch_created_index'
            );

            $table->foreign(['branch_id', 'business_id'], 'product_batch_corrections_branch_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(
                ['product_batch_id', 'business_id', 'branch_id'],
                'product_batch_corrections_batch_business_branch_foreign'
            )
                ->references(['id', 'business_id', 'branch_id'])
                ->on('product_batches')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if ($this->hasBatchCodesSharedAcrossBranches()) {
            throw new \RuntimeException(
                'No es seguro revertir la separación de lotes: existen códigos repetidos del mismo producto en distintas sucursales.'
            );
        }

        Schema::table('product_batch_corrections', function (Blueprint $table): void {
            $table->dropForeign('product_batch_corrections_batch_business_branch_foreign');
            $table->dropForeign('product_batch_corrections_branch_id_business_id_foreign');
            $table->dropIndex('product_batch_corrections_business_branch_product_created_index');
            $table->dropIndex('product_batch_corrections_business_branch_batch_created_index');
            $table->dropColumn('branch_id');
        });

        Schema::table('product_batch_movements', function (Blueprint $table): void {
            $table->dropForeign('product_batch_movements_batch_business_branch_foreign');
            $table->dropForeign('product_batch_movements_branch_id_business_id_foreign');
            $table->dropIndex('product_batch_movements_business_branch_product_created_index');
            $table->dropIndex('product_batch_movements_business_branch_batch_created_index');
            $table->dropColumn('branch_id');
        });

        Schema::table('product_batches', function (Blueprint $table): void {
            $table->dropForeign('product_batches_branch_id_business_id_foreign');
            $table->dropUnique('product_batches_business_branch_product_code_unique');
            $table->dropUnique('product_batches_id_business_branch_unique');
            $table->dropIndex('product_batches_business_branch_product_expires_index');
            $table->dropIndex('product_batches_business_branch_product_quantity_index');
            $table->dropIndex('product_batches_business_branch_expires_index');
            $table->unique(['business_id', 'product_id', 'batch_code'], 'product_batches_business_product_batch_code_unique');
            $table->dropColumn('branch_id');
        });
    }

    private function backfillBatches(): void
    {
        DB::table('product_batches')
            ->whereNull('branch_id')
            ->orderBy('id')
            ->chunkById(500, function ($batches): void {
                foreach ($batches as $batch) {
                    DB::table('product_batches')
                        ->where('id', $batch->id)
                        ->whereNull('branch_id')
                        ->update(['branch_id' => $this->defaultBranchId((int) $batch->business_id)]);
                }
            });
    }

    private function backfillRecordsFromBatch(string $table): void
    {
        DB::table($table)
            ->whereNull('branch_id')
            ->orderBy('id')
            ->chunkById(500, function ($records) use ($table): void {
                $batchBranches = DB::table('product_batches')
                    ->whereIn('id', collect($records)->pluck('product_batch_id')->unique())
                    ->pluck('branch_id', 'id');

                foreach ($records as $record) {
                    $branchId = $batchBranches->get($record->product_batch_id)
                        ?? $this->defaultBranchId((int) $record->business_id);

                    DB::table($table)
                        ->where('id', $record->id)
                        ->whereNull('branch_id')
                        ->update(['branch_id' => $branchId]);
                }
            });
    }

    private function defaultBranchId(int $businessId): int
    {
        return $this->defaultBranches[$businessId] ??= (int) DB::table('branches')
            ->where('business_id', $businessId)
            ->where('is_default', true)
            ->value('id');
    }

    private function hasBatchCodesSharedAcrossBranches(): bool
    {
        return DB::table('product_batches')
            ->select('business_id', 'product_id', 'batch_code')
            ->groupBy('business_id', 'product_id', 'batch_code')
            ->havingRaw('count(*) > 1')
            ->exists();
    }
};
