<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'name']);
            $table->unique(['id', 'business_id'], 'suppliers_id_business_id_unique');
        });

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'slug']);
            $table->index(['business_id', 'name']);
            $table->index(['business_id', 'is_active']);
            $table->unique(['id', 'business_id'], 'categories_id_business_id_unique');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->text('description')->nullable();
            $table->string('barcode')->nullable();
            $table->string('sku')->nullable();
            $table->enum('unit_type', ['unit', 'weight'])->default('unit');
            $table->string('weight_unit', 10)->nullable();
            $table->decimal('sale_price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('stock', 12, 3)->default(0);
            $table->decimal('min_stock', 12, 3)->default(0);
            $table->unsignedInteger('shelf_life_days')->nullable();
            $table->unsignedInteger('expiry_alert_days')->default(15);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'slug']);
            $table->unique(['business_id', 'sku']);
            $table->unique(['business_id', 'barcode']);
            $table->index(['business_id', 'name']);
            $table->index(['business_id', 'is_active']);
            $table->index(['business_id', 'unit_type', 'weight_unit']);
            $table->index(['business_id', 'category_id']);
            $table->index(['business_id', 'supplier_id']);
            $table->unique(['id', 'business_id'], 'products_id_business_id_unique');

            $table->foreign(['category_id', 'business_id'], 'products_category_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('categories');

            $table->foreign(['supplier_id', 'business_id'], 'products_supplier_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('suppliers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('suppliers');
    }
};
