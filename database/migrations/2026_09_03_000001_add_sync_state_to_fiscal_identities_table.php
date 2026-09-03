<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_identities', function (Blueprint $table): void {
            $table->string('sync_status', 20)->default('synced')->after('fiscal_activities');
            $table->string('sync_error', 255)->nullable()->after('sync_status');
            $table->timestamp('synced_at')->nullable()->after('sync_error');
            $table->index('sync_status', 'fiscal_identities_sync_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_identities', function (Blueprint $table): void {
            $table->dropIndex('fiscal_identities_sync_status_index');
            $table->dropColumn(['sync_status', 'sync_error', 'synced_at']);
        });
    }
};
