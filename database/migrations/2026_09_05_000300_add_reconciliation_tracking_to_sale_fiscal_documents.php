<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_fiscal_documents', function (Blueprint $table): void {
            $table->unsignedTinyInteger('reconciliation_attempts')->default(0)->after('attempted_at');
            $table->timestamp('reconciliation_last_attempt_at')->nullable()->after('reconciliation_attempts');
            $table->timestamp('reconciliation_next_attempt_at')->nullable()->after('reconciliation_last_attempt_at');
            $table->timestamp('reconciliation_alerted_at')->nullable()->after('reconciliation_next_attempt_at');
            $table->index(
                ['business_id', 'fiscal_status', 'reconciliation_next_attempt_at'],
                'sale_fiscal_documents_reconciliation_due_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('sale_fiscal_documents', function (Blueprint $table): void {
            $table->dropIndex('sale_fiscal_documents_reconciliation_due_index');
            $table->dropColumn([
                'reconciliation_attempts',
                'reconciliation_last_attempt_at',
                'reconciliation_next_attempt_at',
                'reconciliation_alerted_at',
            ]);
        });
    }
};
