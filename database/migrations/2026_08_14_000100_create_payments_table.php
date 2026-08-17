<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('payment_destination_id')->nullable();
            $table->string('method', 30);
            $table->string('provider', 50)->default('manual');
            $table->string('status', 30)->default('pending');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('ARS');
            $table->string('idempotency_key', 120)->nullable();
            $table->string('external_reference', 160)->nullable();
            $table->string('provider_payment_id', 160)->nullable();
            $table->string('provider_order_id', 160)->nullable();
            $table->string('provider_status', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'sale_id', 'status'], 'payments_business_sale_status_index');
            $table->index(['business_id', 'method', 'status'], 'payments_business_method_status_index');
            $table->index(['business_id', 'provider', 'status'], 'payments_business_provider_status_index');
            $table->unique(['business_id', 'idempotency_key'], 'payments_business_idempotency_unique');
            $table->unique(['business_id', 'provider', 'external_reference'], 'payments_business_provider_external_unique');
            $table->unique(['id', 'business_id'], 'payments_id_business_id_unique');

            $table->foreign(['sale_id', 'business_id'], 'payments_sale_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('sales')
                ->cascadeOnDelete();

            $table->foreign(['created_by', 'business_id'], 'payments_created_by_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('users')
                ->restrictOnDelete();

            $table->foreign(['payment_destination_id', 'business_id'], 'payments_destination_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('business_payment_destinations')
                ->restrictOnDelete();
        });

        $this->backfillExistingSalePayments();
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }

    private function backfillExistingSalePayments(): void
    {
        $validMethods = ['cash', 'transfer', 'qr', 'debit_card', 'credit_card'];

        DB::table('sales')
            ->where('paid_amount', '>', 0)
            ->orderBy('id')
            ->chunkById(500, function ($sales) use ($validMethods): void {
                $rows = [];

                foreach ($sales as $sale) {
                    $method = in_array($sale->payment_method, $validMethods, true)
                        ? $sale->payment_method
                        : 'cash';
                    $approvedAt = $sale->sold_at ?? $sale->created_at ?? now();

                    $rows[] = [
                        'business_id' => $sale->business_id,
                        'sale_id' => $sale->id,
                        'created_by' => $sale->user_id,
                        'payment_destination_id' => $sale->payment_destination_id,
                        'method' => $method,
                        'provider' => 'manual',
                        'status' => 'approved',
                        'amount' => $sale->paid_amount,
                        'currency' => 'ARS',
                        'idempotency_key' => 'legacy-sale-payment:'.$sale->id,
                        'metadata' => json_encode(['source' => 'legacy_sales_payment_fields'], JSON_THROW_ON_ERROR),
                        'requested_at' => $approvedAt,
                        'approved_at' => $approvedAt,
                        'created_at' => $approvedAt,
                        'updated_at' => $approvedAt,
                    ];
                }

                if ($rows !== []) {
                    DB::table('payments')->insert($rows);
                }
            });
    }
};
