<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('global_products', function (Blueprint $table): void {
            $table->id();
            $table->string('barcode')->nullable();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('normalized_name');
            $table->timestamps();

            $table->unique('barcode');
            $table->unique('normalized_name');
            $table->index('name');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('global_products');
    }
};
