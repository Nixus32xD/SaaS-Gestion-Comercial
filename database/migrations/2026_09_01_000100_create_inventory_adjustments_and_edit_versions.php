<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedBigInteger('edit_version')->default(1)->after('is_active');
        });

        Schema::table('branch_product_stocks', function (Blueprint $table): void {
            $table->unsignedBigInteger('edit_version')->default(1)->after('min_stock');
        });

        Schema::create('inventory_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('product_id');
            $table->foreignId('branch_product_stock_id')->constrained('branch_product_stocks')->restrictOnDelete();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('stock_before', 12, 3);
            $table->decimal('stock_after', 12, 3);
            $table->decimal('reserved_stock_snapshot', 12, 3);
            $table->string('reason', 80);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'branch_id', 'product_id', 'created_at'], 'inventory_adjustments_lookup_index');
            $table->index(['branch_product_stock_id', 'created_at'], 'inventory_adjustments_stock_created_index');
            $table->foreign(['branch_id', 'business_id'], 'inventory_adjustments_branch_business_foreign')
                ->references(['id', 'business_id'])->on('branches')->restrictOnDelete();
            $table->foreign(['product_id', 'business_id'], 'inventory_adjustments_product_business_foreign')
                ->references(['id', 'business_id'])->on('products')->restrictOnDelete();
            $table->foreign(['created_by', 'business_id'], 'inventory_adjustments_user_business_foreign')
                ->references(['id', 'business_id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
        Schema::table('branch_product_stocks', fn (Blueprint $table) => $table->dropColumn('edit_version'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('edit_version'));
    }
};
