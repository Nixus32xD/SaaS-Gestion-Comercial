<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Deprecated: tabla historica sin uso activo; el SaaS ya no gestiona credenciales fiscales locales.
        Schema::create('business_fiscal_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('fiscal_business_id', 120)->nullable();
            $table->string('fiscal_credential_id', 80)->nullable();
            $table->string('key_name', 160);
            $table->string('status', 40)->default('pending_certificate');
            $table->longText('csr')->nullable();
            $table->timestamp('certificate_expires_at')->nullable();
            $table->string('last_error_code', 120)->nullable();
            $table->text('last_error_message')->nullable();
            $table->string('last_test_status', 40)->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'status']);
            $table->unique(['business_id', 'key_name'], 'business_fiscal_credentials_business_key_unique');
            $table->unique(['business_id', 'fiscal_credential_id'], 'business_fiscal_credentials_business_external_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_fiscal_credentials');
    }
};
