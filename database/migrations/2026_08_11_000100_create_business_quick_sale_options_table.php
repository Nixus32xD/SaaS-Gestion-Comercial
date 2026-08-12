<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_quick_sale_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('description', 160)->nullable();
            $table->decimal('default_amount', 12, 2)->nullable();
            $table->string('vat_treatment', 30)->default('gravado');
            $table->decimal('vat_rate', 5, 2)->default(21);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['business_id', 'is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_quick_sale_options');
    }
};
