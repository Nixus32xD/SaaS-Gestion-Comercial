<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('fiscal_condition', 40)
                ->default('monotributo')
                ->after('fiscal_cuit');

            $table->index('fiscal_condition', 'businesses_fiscal_condition_index');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->json('fiscal_customer')->nullable()->after('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn('fiscal_customer');
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex('businesses_fiscal_condition_index');
            $table->dropColumn('fiscal_condition');
        });
    }
};
