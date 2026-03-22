<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id');
            $table->string('batch_code', 80);
            $table->date('expires_at')->nullable();
            $table->decimal('quantity', 12, 3)->default(0);
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'product_id', 'batch_code'], 'product_batches_business_product_batch_code_unique');
            $table->index(['business_id', 'product_id', 'expires_at'], 'product_batches_business_product_expires_index');
            $table->index(['business_id', 'product_id', 'quantity'], 'product_batches_business_product_quantity_index');
            $table->index(['business_id', 'expires_at'], 'product_batches_business_expires_index');
            $table->unique(['id', 'business_id'], 'product_batches_id_business_id_unique');

            $table->foreign(['product_id', 'business_id'], 'product_batches_product_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('products')
                ->cascadeOnDelete();
        });

        Schema::create('product_batch_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('product_batch_id');
            $table->unsignedBigInteger('product_id');
            $table->string('type', 40);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->decimal('batch_before', 12, 3);
            $table->decimal('batch_after', 12, 3);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'product_id', 'created_at'], 'product_batch_movements_business_product_created_index');
            $table->index(['business_id', 'product_batch_id', 'created_at'], 'product_batch_movements_business_batch_created_index');
            $table->index(['reference_type', 'reference_id'], 'product_batch_movements_reference_index');

            $table->foreign(['product_batch_id', 'business_id'], 'product_batch_movements_batch_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('product_batches')
                ->cascadeOnDelete();

            $table->foreign(['product_id', 'business_id'], 'product_batch_movements_product_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign(['created_by', 'business_id'], 'product_batch_movements_created_by_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batch_movements');
        Schema::dropIfExists('product_batches');
    }
};
