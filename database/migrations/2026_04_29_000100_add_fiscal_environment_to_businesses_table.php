<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('fiscal_environment', 20)
                ->default('testing')
                ->after('fiscal_external_business_id');

            $table->index(['fiscal_environment', 'fiscal_enabled'], 'businesses_fiscal_environment_enabled_index');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex('businesses_fiscal_environment_enabled_index');
            $table->dropColumn('fiscal_environment');
        });
    }
};
