<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_mercado_pago_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->unique()->constrained('businesses')->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->string('environment', 20)->default('testing');
            $table->text('public_key')->nullable();
            $table->text('access_token')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('point_terminal_id', 160)->nullable();
            $table->string('point_store_id', 80)->nullable();
            $table->string('point_pos_id', 80)->nullable();
            $table->string('point_external_store_id', 80)->nullable();
            $table->string('point_external_pos_id', 80)->nullable();
            $table->string('point_expiration_time', 20)->default('PT15M');
            $table->string('point_print_on_terminal', 30)->default('no_ticket');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_enabled', 'environment'], 'business_mp_credentials_enabled_env_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_mercado_pago_credentials');
    }
};
