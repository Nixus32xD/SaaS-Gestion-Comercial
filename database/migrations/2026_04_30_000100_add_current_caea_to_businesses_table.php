<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('fiscal_caea_code', 14)->nullable()->after('fiscal_authorization_mode');
            $table->string('fiscal_caea_period', 6)->nullable()->after('fiscal_caea_code');
            $table->unsignedTinyInteger('fiscal_caea_order')->nullable()->after('fiscal_caea_period');
            $table->date('fiscal_caea_from')->nullable()->after('fiscal_caea_order');
            $table->date('fiscal_caea_to')->nullable()->after('fiscal_caea_from');
            $table->date('fiscal_caea_due_date')->nullable()->after('fiscal_caea_to');
            $table->date('fiscal_caea_report_deadline')->nullable()->after('fiscal_caea_due_date');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropColumn([
                'fiscal_caea_code',
                'fiscal_caea_period',
                'fiscal_caea_order',
                'fiscal_caea_from',
                'fiscal_caea_to',
                'fiscal_caea_due_date',
                'fiscal_caea_report_deadline',
            ]);
        });
    }
};
