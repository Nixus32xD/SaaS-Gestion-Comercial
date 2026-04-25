<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->boolean('fiscal_enabled')->default(false)->after('subscription_notes');
            $table->string('fiscal_external_business_id')->nullable()->after('fiscal_enabled');
            $table->unsignedInteger('fiscal_point_of_sale')->nullable()->after('fiscal_external_business_id');
            $table->string('fiscal_document_type', 40)->nullable()->after('fiscal_point_of_sale');
            $table->unsignedSmallInteger('fiscal_cbte_type')->nullable()->after('fiscal_document_type');
            $table->unsignedTinyInteger('fiscal_concept')->nullable()->after('fiscal_cbte_type');
            $table->json('fiscal_activities')->nullable()->after('fiscal_concept');

            $table->index(['fiscal_enabled', 'fiscal_external_business_id'], 'businesses_fiscal_enabled_external_index');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex('businesses_fiscal_enabled_external_index');
            $table->dropColumn([
                'fiscal_enabled',
                'fiscal_external_business_id',
                'fiscal_point_of_sale',
                'fiscal_document_type',
                'fiscal_cbte_type',
                'fiscal_concept',
                'fiscal_activities',
            ]);
        });
    }
};
