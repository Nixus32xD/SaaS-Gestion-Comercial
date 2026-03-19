<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('feature', 80);
            $table->boolean('is_enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'feature']);
            $table->index(['business_id', 'is_enabled']);
        });

        Schema::create('business_sale_sectors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'is_active']);
            $table->index(['business_id', 'sort_order']);
            $table->unique(['id', 'business_id'], 'business_sale_sectors_id_business_id_unique');
        });

        Schema::create('business_payment_destinations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('account_holder')->nullable();
            $table->string('reference')->nullable();
            $table->string('account_number')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['business_id', 'name']);
            $table->index(['business_id', 'is_active']);
            $table->index(['business_id', 'sort_order']);
            $table->unique(['id', 'business_id'], 'business_payment_destinations_id_business_id_unique');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->unsignedBigInteger('sale_sector_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('payment_destination_id')->nullable()->after('payment_method');

            $table->index(['business_id', 'sale_sector_id']);
            $table->index(['business_id', 'payment_destination_id']);

            $table->foreign(['sale_sector_id', 'business_id'], 'sales_sale_sector_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('business_sale_sectors')
                ->restrictOnDelete();

            $table->foreign(['payment_destination_id', 'business_id'], 'sales_payment_destination_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('business_payment_destinations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign('sales_payment_destination_id_business_id_foreign');
            $table->dropForeign('sales_sale_sector_id_business_id_foreign');
            $table->dropIndex(['business_id', 'payment_destination_id']);
            $table->dropIndex(['business_id', 'sale_sector_id']);
            $table->dropColumn(['sale_sector_id', 'payment_destination_id']);
        });

        Schema::dropIfExists('business_payment_destinations');
        Schema::dropIfExists('business_sale_sectors');
        Schema::dropIfExists('business_features');
    }
};
