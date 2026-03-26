<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table): void {
            $table->string('implementation_plan_code', 40)->nullable()->after('address');
            $table->decimal('implementation_amount', 12, 2)->nullable()->after('implementation_plan_code');
            $table->string('maintenance_plan_code', 40)->nullable()->after('implementation_amount');
            $table->decimal('maintenance_amount', 12, 2)->nullable()->after('maintenance_plan_code');
            $table->date('maintenance_started_at')->nullable()->after('maintenance_amount');
            $table->date('maintenance_ends_at')->nullable()->after('maintenance_started_at');
            $table->unsignedTinyInteger('subscription_grace_days')->default(7)->after('maintenance_ends_at');
            $table->text('subscription_notes')->nullable()->after('subscription_grace_days');

            $table->index(['maintenance_plan_code', 'maintenance_ends_at'], 'businesses_maintenance_plan_ends_index');
        });

        Schema::create('business_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('plan_code', 40)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('paid_at');
            $table->date('coverage_ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'type']);
            $table->index(['business_id', 'paid_at']);
            $table->index(['business_id', 'coverage_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_payments');

        Schema::table('businesses', function (Blueprint $table): void {
            $table->dropIndex('businesses_maintenance_plan_ends_index');
            $table->dropColumn([
                'implementation_plan_code',
                'implementation_amount',
                'maintenance_plan_code',
                'maintenance_amount',
                'maintenance_started_at',
                'maintenance_ends_at',
                'subscription_grace_days',
                'subscription_notes',
            ]);
        });
    }
};
