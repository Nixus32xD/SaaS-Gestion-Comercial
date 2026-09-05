<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table): void {
            // Nullable preserves historical adjustments created before idempotency.
            $table->string('idempotency_key', 120)->nullable()->after('notes');
            $table->char('request_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->unique(['business_id', 'idempotency_key'], 'inventory_adjustments_business_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_adjustments', function (Blueprint $table): void {
            $table->dropUnique('inventory_adjustments_business_idempotency_unique');
            $table->dropColumn(['idempotency_key', 'request_fingerprint']);
        });
    }
};
