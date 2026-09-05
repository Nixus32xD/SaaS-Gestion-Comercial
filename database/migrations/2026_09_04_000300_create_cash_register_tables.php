<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('opened_by');
            $table->timestamp('opened_at');
            $table->decimal('opening_amount', 12, 2);
            $table->text('opening_notes')->nullable();
            $table->string('status', 20)->default('open');
            // Nullable unique markers permit history while enforcing one physical drawer per branch.
            $table->unsignedTinyInteger('open_marker')->nullable();
            $table->unsignedBigInteger('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_amount_at_close', 12, 2)->nullable();
            $table->decimal('counted_amount', 12, 2)->nullable();
            $table->decimal('difference_amount', 12, 2)->nullable();
            $table->text('closing_notes')->nullable();
            $table->timestamps();

            $table->unique(['id', 'business_id'], 'cash_sessions_id_business_unique');
            $table->unique(['business_id', 'branch_id', 'open_marker'], 'cash_sessions_open_branch_unique');
            $table->index(['business_id', 'branch_id', 'status'], 'cash_sessions_business_branch_status_index');
            $table->index(['business_id', 'opened_at'], 'cash_sessions_business_opened_index');

            $table->foreign(['branch_id', 'business_id'], 'cash_sessions_branch_business_foreign')
                ->references(['id', 'business_id'])->on('branches')->restrictOnDelete();
            $table->foreign(['opened_by', 'business_id'], 'cash_sessions_opener_business_foreign')
                ->references(['id', 'business_id'])->on('users')->restrictOnDelete();
            $table->foreign(['closed_by', 'business_id'], 'cash_sessions_closer_business_foreign')
                ->references(['id', 'business_id'])->on('users')->restrictOnDelete();
        });

        Schema::create('cash_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('cash_session_id');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type', 30);
            // Signed amount: income/sales are positive; expenses/refunds are negative.
            $table->decimal('amount', 12, 2);
            $table->string('reference_type', 160)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->unique(['business_id', 'reference_type', 'reference_id'], 'cash_movements_business_reference_unique');
            $table->index(['business_id', 'branch_id', 'occurred_at'], 'cash_movements_business_branch_occurred_index');
            $table->index(['business_id', 'cash_session_id', 'occurred_at'], 'cash_movements_business_session_occurred_index');

            $table->foreign(['cash_session_id', 'business_id'], 'cash_movements_session_business_foreign')
                ->references(['id', 'business_id'])->on('cash_sessions')->restrictOnDelete();
            $table->foreign(['branch_id', 'business_id'], 'cash_movements_branch_business_foreign')
                ->references(['id', 'business_id'])->on('branches')->restrictOnDelete();
            $table->foreign(['created_by', 'business_id'], 'cash_movements_creator_business_foreign')
                ->references(['id', 'business_id'])->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
    }
};
