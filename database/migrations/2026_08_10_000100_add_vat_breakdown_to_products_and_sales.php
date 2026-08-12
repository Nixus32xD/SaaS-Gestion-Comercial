<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('vat_treatment', 20)->default('gravado')->after('cost_price');
            $table->decimal('vat_rate', 5, 2)->default(21)->after('vat_treatment');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->decimal('fiscal_net_amount', 12, 2)->default(0)->after('total');
            $table->decimal('fiscal_vat_amount', 12, 2)->default(0)->after('fiscal_net_amount');
            $table->decimal('fiscal_exempt_amount', 12, 2)->default(0)->after('fiscal_vat_amount');
            $table->decimal('fiscal_non_taxed_amount', 12, 2)->default(0)->after('fiscal_exempt_amount');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->string('vat_treatment', 20)->default('gravado')->after('subtotal');
            $table->decimal('vat_rate', 5, 2)->default(21)->after('vat_treatment');
            $table->decimal('net_amount', 12, 2)->default(0)->after('vat_rate');
            $table->decimal('vat_amount', 12, 2)->default(0)->after('net_amount');
            $table->decimal('exempt_amount', 12, 2)->default(0)->after('vat_amount');
            $table->decimal('non_taxed_amount', 12, 2)->default(0)->after('exempt_amount');
            $table->decimal('gross_amount', 12, 2)->default(0)->after('non_taxed_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropColumn([
                'vat_treatment',
                'vat_rate',
                'net_amount',
                'vat_amount',
                'exempt_amount',
                'non_taxed_amount',
                'gross_amount',
            ]);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn([
                'fiscal_net_amount',
                'fiscal_vat_amount',
                'fiscal_exempt_amount',
                'fiscal_non_taxed_amount',
            ]);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['vat_treatment', 'vat_rate']);
        });
    }
};
