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
        Schema::table('products', function (Blueprint $table): void {
            if (! Schema::hasColumn('products', 'weight_unit')) {
                $table->string('weight_unit', 10)->nullable()->after('unit_type');
                $table->index(['business_id', 'unit_type', 'weight_unit']);
            }
        });

        DB::table('products')
            ->where('unit_type', 'weight')
            ->whereNull('weight_unit')
            ->update(['weight_unit' => 'kg']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'weight_unit')) {
                $table->dropIndex('products_business_id_unit_type_weight_unit_index');
                $table->dropColumn('weight_unit');
            }
        });
    }
};
