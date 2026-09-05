<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->string('supplier_cuit', 11)->nullable()->after('supplier_id');
            $table->string('fiscal_document_type', 30)->nullable()->after('purchase_number');
            $table->unsignedInteger('fiscal_point_of_sale')->nullable()->after('fiscal_document_type');
            $table->unsignedBigInteger('fiscal_number')->nullable()->after('fiscal_point_of_sale');
            $table->date('fiscal_voucher_date')->nullable()->after('fiscal_number');
            $table->decimal('fiscal_net_amount', 14, 2)->nullable()->after('total');
            $table->decimal('fiscal_vat_amount', 14, 2)->nullable()->after('fiscal_net_amount');
            $table->decimal('fiscal_exempt_amount', 14, 2)->nullable()->after('fiscal_vat_amount');
            $table->decimal('fiscal_non_taxed_amount', 14, 2)->nullable()->after('fiscal_exempt_amount');
            $table->decimal('fiscal_other_taxes_amount', 14, 2)->nullable()->after('fiscal_non_taxed_amount');
            $table->decimal('fiscal_total_amount', 14, 2)->nullable()->after('fiscal_other_taxes_amount');

            $table->index(['business_id', 'branch_id', 'fiscal_voucher_date'], 'purchases_fiscal_period_index');
            $table->unique([
                'business_id', 'supplier_cuit', 'fiscal_document_type', 'fiscal_point_of_sale', 'fiscal_number',
            ], 'purchases_fiscal_supplier_voucher_unique');
        });

        Schema::create('purchase_fiscal_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('purchase_id');
            $table->string('vat_treatment', 20);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
            $table->decimal('exempt_amount', 14, 2)->default(0);
            $table->decimal('non_taxed_amount', 14, 2)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->timestamps();

            $table->index(['business_id', 'purchase_id']);
            $table->foreign(['purchase_id', 'business_id'], 'purchase_fiscal_items_purchase_business_foreign')
                ->references(['id', 'business_id'])
                ->on('purchases')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_fiscal_items');

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropUnique('purchases_fiscal_supplier_voucher_unique');
            $table->dropIndex('purchases_fiscal_period_index');
            $table->dropColumn([
                'supplier_cuit', 'fiscal_document_type', 'fiscal_point_of_sale', 'fiscal_number', 'fiscal_voucher_date',
                'fiscal_net_amount', 'fiscal_vat_amount', 'fiscal_exempt_amount', 'fiscal_non_taxed_amount',
                'fiscal_other_taxes_amount', 'fiscal_total_amount',
            ]);
        });
    }
};
