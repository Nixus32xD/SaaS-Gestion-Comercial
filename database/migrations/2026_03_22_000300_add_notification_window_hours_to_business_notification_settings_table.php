<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_notification_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('notification_window_start_hour')
                ->default(9)
                ->after('minimum_hours_between_alerts');
            $table->unsignedTinyInteger('notification_window_end_hour')
                ->default(18)
                ->after('notification_window_start_hour');
        });
    }

    public function down(): void
    {
        Schema::table('business_notification_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'notification_window_start_hour',
                'notification_window_end_hour',
            ]);
        });
    }
};
