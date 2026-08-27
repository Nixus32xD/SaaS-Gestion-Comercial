<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('point_status', 20)->nullable()->after('stock_reservation_status');
            $table->string('point_status_reason', 80)->nullable()->after('point_status');
            $table->timestamp('point_status_changed_at')->nullable()->after('point_status_reason');
            $table->index(['business_id', 'point_status'], 'sales_business_point_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_business_point_status_index');
            $table->dropColumn([
                'point_status',
                'point_status_reason',
                'point_status_changed_at',
            ]);
        });
    }
};
