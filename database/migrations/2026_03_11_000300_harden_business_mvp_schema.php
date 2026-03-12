<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('business_document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('type', 40);
            $table->unsignedInteger('current_number')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'type']);
        });

        $this->seedExistingSequences();

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->foreignId('business_id')->nullable()->after('id')->constrained('businesses')->cascadeOnDelete();
            $table->index(['business_id', 'product_id']);
        });

        DB::statement('
            UPDATE sale_items
            INNER JOIN sales ON sales.id = sale_items.sale_id
            SET sale_items.business_id = sales.business_id
            WHERE sale_items.business_id IS NULL
        ');

        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->foreignId('business_id')->nullable()->after('id')->constrained('businesses')->cascadeOnDelete();
            $table->index(['business_id', 'product_id']);
        });

        DB::statement('
            UPDATE purchase_items
            INNER JOIN purchases ON purchases.id = purchase_items.purchase_id
            SET purchase_items.business_id = purchases.business_id
            WHERE purchase_items.business_id IS NULL
        ');

        DB::statement('ALTER TABLE sale_items MODIFY business_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE purchase_items MODIFY business_id BIGINT UNSIGNED NOT NULL');

        DB::table('products')->where('sku', '')->update(['sku' => null]);
        DB::table('products')->where('barcode', '')->update(['barcode' => null]);

        $this->abortIfDuplicatesExist('products', 'sku', 'No se puede crear unique(business_id, sku): hay SKUs duplicados por comercio.');
        $this->abortIfDuplicatesExist('products', 'barcode', 'No se puede crear unique(business_id, barcode): hay codigos de barra duplicados por comercio.');

        Schema::table('sales', function (Blueprint $table): void {
            $table->unique(['business_id', 'sale_number']);
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->unique(['business_id', 'purchase_number']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'sku']);
            $table->dropUnique(['business_id', 'barcode']);
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'purchase_number']);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique(['business_id', 'sale_number']);
        });

        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'product_id']);
            $table->dropConstrainedForeignId('business_id');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropIndex(['business_id', 'product_id']);
            $table->dropConstrainedForeignId('business_id');
        });

        Schema::dropIfExists('business_document_sequences');
    }

    private function abortIfDuplicatesExist(string $table, string $column, string $message): void
    {
        $duplicateExists = DB::table($table)
            ->select('business_id', $column)
            ->whereNotNull($column)
            ->groupBy('business_id', $column)
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateExists) {
            throw new RuntimeException($message);
        }
    }

    private function seedExistingSequences(): void
    {
        $sales = DB::table('sales')
            ->select('business_id', DB::raw('MAX(COALESCE(CAST(SUBSTRING(sale_number, 3) AS UNSIGNED), 0)) as current_number'))
            ->whereNotNull('sale_number')
            ->groupBy('business_id')
            ->get()
            ->map(fn ($row) => [
                'business_id' => $row->business_id,
                'type' => 'sale',
                'current_number' => (int) $row->current_number,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        $purchases = DB::table('purchases')
            ->select('business_id', DB::raw('MAX(COALESCE(CAST(SUBSTRING(purchase_number, 3) AS UNSIGNED), 0)) as current_number'))
            ->whereNotNull('purchase_number')
            ->groupBy('business_id')
            ->get()
            ->map(fn ($row) => [
                'business_id' => $row->business_id,
                'type' => 'purchase',
                'current_number' => (int) $row->current_number,
                'created_at' => now(),
                'updated_at' => now(),
            ])
            ->all();

        $payload = [...$sales, ...$purchases];

        if ($payload !== []) {
            DB::table('business_document_sequences')->insert($payload);
        }
    }
};
