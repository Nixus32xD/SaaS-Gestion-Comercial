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
        Schema::create('business_document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('type', 40);
            $table->unsignedInteger('current_number')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'type']);
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->string('sale_number')->nullable();
            $table->string('payment_method', 20)->default('cash');
            $table->decimal('amount_received', 12, 2)->nullable();
            $table->decimal('change_amount', 12, 2)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('sold_at');
            $table->timestamps();

            $table->index(['business_id', 'sold_at']);
            $table->index(['business_id', 'payment_method']);
            $table->unique(['business_id', 'sale_number']);
            $table->unique(['id', 'business_id'], 'sales_id_business_id_unique');

            $table->foreign(['user_id', 'business_id'], 'sales_user_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();

            $table->index(['business_id', 'product_id']);

            $table->foreign(['sale_id', 'business_id'], 'sale_items_sale_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('sales')
                ->cascadeOnDelete();

            $table->foreign(['product_id', 'business_id'], 'sale_items_product_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('products')
                ->restrictOnDelete();
        });

        Schema::create('purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('purchase_number')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('purchased_at');
            $table->timestamps();

            $table->index(['business_id', 'purchased_at']);
            $table->unique(['business_id', 'purchase_number']);
            $table->unique(['id', 'business_id'], 'purchases_id_business_id_unique');

            $table->foreign(['user_id', 'business_id'], 'purchases_user_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(['supplier_id', 'business_id'], 'purchases_supplier_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('suppliers')
                ->restrictOnDelete();
        });

        Schema::create('purchase_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('purchase_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_name');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 12, 2);
            $table->decimal('subtotal', 12, 2);
            $table->date('expires_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'product_id']);
            $table->index('expires_at');

            $table->foreign(['purchase_id', 'business_id'], 'purchase_items_purchase_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('purchases')
                ->cascadeOnDelete();

            $table->foreign(['product_id', 'business_id'], 'purchase_items_product_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('products')
                ->restrictOnDelete();
        });

        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('type');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('stock_before', 12, 3);
            $table->decimal('stock_after', 12, 3);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index(['business_id', 'created_at']);
            $table->index(['business_id', 'product_id']);
            $table->index(['reference_type', 'reference_id']);

            $table->foreign(['product_id', 'business_id'], 'stock_movements_product_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('products')
                ->restrictOnDelete();

            $table->foreign(['created_by', 'business_id'], 'stock_movements_created_by_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('users')
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('business_document_sequences');
    }
};
