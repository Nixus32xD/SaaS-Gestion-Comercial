<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('from_branch_id');
            $table->unsignedBigInteger('to_branch_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('created_by');
            $table->uuid('reference');
            $table->string('idempotency_key', 120);
            $table->decimal('quantity', 12, 3);
            $table->decimal('from_stock_before', 12, 3);
            $table->decimal('from_stock_after', 12, 3);
            $table->decimal('from_reserved_stock_snapshot', 12, 3);
            $table->decimal('to_stock_before', 12, 3);
            $table->decimal('to_stock_after', 12, 3);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['id', 'business_id'], 'inventory_transfers_id_business_unique');
            $table->unique(['business_id', 'reference'], 'inventory_transfers_business_reference_unique');
            $table->unique(['business_id', 'idempotency_key'], 'inventory_transfers_business_idempotency_unique');
            $table->index(['business_id', 'from_branch_id', 'created_at'], 'inventory_transfers_business_from_created_index');
            $table->index(['business_id', 'to_branch_id', 'created_at'], 'inventory_transfers_business_to_created_index');
            $table->index(['business_id', 'product_id', 'created_at'], 'inventory_transfers_business_product_created_index');

            $table->foreign(['from_branch_id', 'business_id'], 'inventory_transfers_from_branch_business_foreign')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['to_branch_id', 'business_id'], 'inventory_transfers_to_branch_business_foreign')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->restrictOnDelete();
            $table->foreign(['product_id', 'business_id'], 'inventory_transfers_product_business_foreign')
                ->references(['id', 'business_id'])
                ->on('products')
                ->restrictOnDelete();
            $table->foreign(['created_by', 'business_id'], 'inventory_transfers_creator_business_foreign')
                ->references(['id', 'business_id'])
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::create('inventory_transfer_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('inventory_transfer_id');
            $table->unsignedBigInteger('source_product_batch_id');
            $table->unsignedBigInteger('destination_product_batch_id');
            $table->string('source_batch_code', 80);
            $table->string('destination_batch_code', 80);
            $table->date('expires_at')->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();

            $table->unique(['inventory_transfer_id', 'source_product_batch_id'], 'inventory_transfer_batches_transfer_source_unique');
            $table->index(['business_id', 'inventory_transfer_id'], 'inventory_transfer_batches_business_transfer_index');

            $table->foreign(['inventory_transfer_id', 'business_id'], 'inventory_transfer_batches_transfer_business_foreign')
                ->references(['id', 'business_id'])
                ->on('inventory_transfers')
                ->cascadeOnDelete();
            $table->foreign(['source_product_batch_id', 'business_id'], 'inventory_transfer_batches_source_batch_business_foreign')
                ->references(['id', 'business_id'])
                ->on('product_batches')
                ->restrictOnDelete();
            $table->foreign(['destination_product_batch_id', 'business_id'], 'inventory_transfer_batches_destination_batch_business_foreign')
                ->references(['id', 'business_id'])
                ->on('product_batches')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_batches');
        Schema::dropIfExists('inventory_transfers');
    }
};
