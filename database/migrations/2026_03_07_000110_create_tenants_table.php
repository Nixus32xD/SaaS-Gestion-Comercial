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
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('business_id')
                ->nullable()
                ->after('id')
                ->constrained('businesses')
                ->nullOnDelete();

            $table->string('role', 30)->default('admin')->after('password');
            $table->boolean('is_active')->default(true)->after('role');
            $table->timestamp('last_login_at')->nullable()->after('is_active');

            $table->index('role');
            $table->index('is_active');
            $table->index(['business_id', 'role']);
            $table->unique(['id', 'business_id'], 'users_id_business_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_id_business_id_unique');
            $table->dropIndex(['business_id', 'role']);
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn(['role', 'is_active', 'last_login_at']);
        });
    }
};
