<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('fiscal_authorization_mode', 10)
                ->default('cae')
                ->after('fiscal_concept');
        });

        Schema::table('sale_fiscal_documents', function (Blueprint $table): void {
            $table->string('authorization_type', 10)->nullable()->after('fiscal_cae_expires_at');
            $table->string('authorization_code', 40)->nullable()->after('authorization_type');
            $table->date('authorization_expires_at')->nullable()->after('authorization_code');
            $table->string('caea_period', 20)->nullable()->after('authorization_expires_at');
            $table->unsignedSmallInteger('caea_order')->nullable()->after('caea_period');
            $table->string('caea_report_status', 30)->nullable()->after('caea_order');
            $table->timestamp('caea_reported_at')->nullable()->after('caea_report_status');

            $table->index(['business_id', 'authorization_type'], 'sale_fiscal_docs_business_auth_type_index');
            $table->index(['business_id', 'caea_report_status'], 'sale_fiscal_docs_business_caea_report_index');
        });
    }

    public function down(): void
    {
        Schema::table('sale_fiscal_documents', function (Blueprint $table): void {
            $table->dropIndex('sale_fiscal_docs_business_auth_type_index');
            $table->dropIndex('sale_fiscal_docs_business_caea_report_index');
            $table->dropColumn([
                'authorization_type',
                'authorization_code',
                'authorization_expires_at',
                'caea_period',
                'caea_order',
                'caea_report_status',
                'caea_reported_at',
            ]);
        });

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn('fiscal_authorization_mode');
        });
    }
};
