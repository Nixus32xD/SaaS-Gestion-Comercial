<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider', 50);
            $table->string('event_key', 180);
            $table->string('event_type', 80)->nullable();
            $table->string('resource_id', 180)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_key'], 'payment_events_provider_event_unique');
            $table->index(['provider', 'resource_id'], 'payment_events_provider_resource_index');
            $table->index(['business_id', 'payment_id'], 'payment_events_business_payment_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_events');
    }
};
