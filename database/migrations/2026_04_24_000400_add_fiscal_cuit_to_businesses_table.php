<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('fiscal_cuit', 11)->nullable()->after('fiscal_external_business_id');

            $table->index('fiscal_cuit', 'businesses_fiscal_cuit_index');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex('businesses_fiscal_cuit_index');
            $table->dropColumn('fiscal_cuit');
        });
    }
};
