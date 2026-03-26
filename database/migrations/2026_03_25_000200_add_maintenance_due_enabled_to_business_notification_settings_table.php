<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_notification_settings', function (Blueprint $table): void {
            $table->boolean('maintenance_due_enabled')
                ->default(true)
                ->after('expiration_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('business_notification_settings', function (Blueprint $table): void {
            $table->dropColumn('maintenance_due_enabled');
        });
    }
};
