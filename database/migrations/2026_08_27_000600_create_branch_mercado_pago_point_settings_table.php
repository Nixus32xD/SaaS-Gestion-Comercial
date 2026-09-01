<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_mercado_pago_point_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('branch_id');
            $table->boolean('is_enabled')->default(true);
            $table->string('point_terminal_id', 160)->nullable();
            $table->string('point_store_id', 80)->nullable();
            $table->string('point_pos_id', 80)->nullable();
            $table->string('point_external_store_id', 80)->nullable();
            $table->string('point_external_pos_id', 80)->nullable();
            $table->string('point_expiration_time', 20)->nullable();
            $table->string('point_print_on_terminal', 30)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['business_id', 'branch_id'], 'branch_mp_point_business_branch_unique');
            $table->index(['business_id', 'is_enabled'], 'branch_mp_point_business_enabled_index');
            $table->foreign(['business_id'], 'branch_mp_point_business_foreign')
                ->references(['id'])
                ->on('businesses')
                ->cascadeOnDelete();
            $table->foreign(['branch_id', 'business_id'], 'branch_mp_point_branch_business_foreign')
                ->references(['id', 'business_id'])
                ->on('branches')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_mercado_pago_point_settings');
    }
};
