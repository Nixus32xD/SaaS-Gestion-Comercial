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
        Schema::table('sales', function (Blueprint $table): void {
            if (! Schema::hasColumn('sales', 'payment_method')) {
                $table->string('payment_method', 20)->default('cash')->after('sale_number');
                $table->index(['business_id', 'payment_method']);
            }

            if (! Schema::hasColumn('sales', 'amount_received')) {
                $table->decimal('amount_received', 12, 2)->nullable()->after('payment_method');
            }

            if (! Schema::hasColumn('sales', 'change_amount')) {
                $table->decimal('change_amount', 12, 2)->nullable()->after('amount_received');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('sales', 'change_amount')) {
                $columns[] = 'change_amount';
            }

            if (Schema::hasColumn('sales', 'amount_received')) {
                $columns[] = 'amount_received';
            }

            if (Schema::hasColumn('sales', 'payment_method')) {
                $table->dropIndex('sales_business_id_payment_method_index');
                $columns[] = 'payment_method';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
