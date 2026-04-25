<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_fiscal_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedInteger('attempt_number')->default(1);
            $table->string('fiscal_document_id')->nullable();
            $table->string('fiscal_status', 30)->default('processing');
            $table->unsignedInteger('fiscal_point_of_sale')->nullable();
            $table->unsignedSmallInteger('fiscal_cbte_type')->nullable();
            $table->unsignedInteger('fiscal_number')->nullable();
            $table->string('fiscal_cae', 40)->nullable();
            $table->date('fiscal_cae_expires_at')->nullable();
            $table->string('fiscal_error_code', 80)->nullable();
            $table->text('fiscal_error_message')->nullable();
            $table->string('fiscal_idempotency_key', 180);
            $table->json('fiscal_payload')->nullable();
            $table->json('fiscal_response')->nullable();
            $table->json('fiscal_observations')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'sale_id']);
            $table->index(['business_id', 'fiscal_status']);
            $table->unique(['business_id', 'fiscal_idempotency_key'], 'sale_fiscal_docs_business_idempotency_unique');
            $table->unique(['business_id', 'fiscal_document_id'], 'sale_fiscal_docs_business_external_unique');
            $table->unique(['id', 'business_id'], 'sale_fiscal_documents_id_business_id_unique');

            $table->foreign(['sale_id', 'business_id'], 'sale_fiscal_documents_sale_business_foreign')
                ->references(['id', 'business_id'])
                ->on('sales')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_fiscal_documents');
    }
};
