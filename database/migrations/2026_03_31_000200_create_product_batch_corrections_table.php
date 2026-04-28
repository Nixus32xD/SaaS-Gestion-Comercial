<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batch_corrections', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('product_batch_id');
            $table->unsignedBigInteger('corrected_by')->nullable();
            $table->string('previous_batch_code', 80);
            $table->string('new_batch_code', 80);
            $table->date('previous_expires_at')->nullable();
            $table->date('new_expires_at')->nullable();
            $table->decimal('previous_unit_cost', 10, 2)->nullable();
            $table->decimal('new_unit_cost', 10, 2)->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->index(['business_id', 'product_id', 'created_at'], 'product_batch_corrections_business_product_created_index');
            $table->index(['business_id', 'product_batch_id', 'created_at'], 'product_batch_corrections_business_batch_created_index');

            $table->foreign(['product_id', 'business_id'], 'product_batch_corrections_product_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('products')
                ->cascadeOnDelete();

            $table->foreign(['product_batch_id', 'business_id'], 'product_batch_corrections_batch_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('product_batches')
                ->cascadeOnDelete();

            $table->foreign(['corrected_by', 'business_id'], 'product_batch_corrections_corrected_by_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batch_corrections');
    }
};
