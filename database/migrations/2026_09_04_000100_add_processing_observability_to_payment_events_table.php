<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_events', function (Blueprint $table): void {
            $table->timestamp('processing_at')->nullable()->after('payload');
            $table->text('last_error')->nullable()->after('processed_at');
            $table->index(['provider', 'processed_at', 'processing_at'], 'payment_events_processing_index');
        });
    }

    public function down(): void
    {
        Schema::table('payment_events', function (Blueprint $table): void {
            $table->dropIndex('payment_events_processing_index');
            $table->dropColumn(['processing_at', 'last_error']);
        });
    }
};
