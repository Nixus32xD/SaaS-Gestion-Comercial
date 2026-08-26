<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('reserved_stock', 12, 3)->default(0)->after('stock');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->string('stock_reservation_status', 20)->nullable()->after('payment_status');
            $table->index(['business_id', 'stock_reservation_status'], 'sales_business_reservation_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex('sales_business_reservation_status_index');
            $table->dropColumn('stock_reservation_status');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('reserved_stock');
        });
    }
};
