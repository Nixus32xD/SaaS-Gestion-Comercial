<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->boolean('send_to_business_email')->default(true);
            $table->boolean('send_to_admin_users')->default(true);
            $table->json('extra_recipients')->nullable();
            $table->boolean('low_stock_enabled')->default(true);
            $table->boolean('expiration_enabled')->default(true);
            $table->unsignedSmallInteger('minimum_hours_between_alerts')->default(12);
            $table->timestamps();

            $table->unique('business_id', 'business_notification_settings_business_unique');
        });

        Schema::create('business_notification_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('notification_type', 80);
            $table->string('channel', 40)->default('mail');
            $table->string('status', 40)->default('sent');
            $table->string('signature', 64);
            $table->string('subject')->nullable();
            $table->json('recipients')->nullable();
            $table->json('payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('attempted_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'notification_type', 'attempted_at'], 'business_notification_dispatches_business_type_attempted_index');
            $table->index(['business_id', 'notification_type', 'signature'], 'business_notification_dispatches_business_type_signature_index');
            $table->index(['business_id', 'status', 'attempted_at'], 'business_notification_dispatches_business_status_attempted_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_notification_dispatches');
        Schema::dropIfExists('business_notification_settings');
    }
};
