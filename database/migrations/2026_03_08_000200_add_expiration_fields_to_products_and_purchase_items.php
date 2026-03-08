<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('shelf_life_days')->nullable()->after('min_stock');
            $table->unsignedInteger('expiry_alert_days')->default(15)->after('shelf_life_days');
        });

        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->date('expires_at')->nullable()->after('subtotal');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table): void {
            $table->dropIndex(['expires_at']);
            $table->dropColumn('expires_at');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['shelf_life_days', 'expiry_alert_days']);
        });
    }
};
