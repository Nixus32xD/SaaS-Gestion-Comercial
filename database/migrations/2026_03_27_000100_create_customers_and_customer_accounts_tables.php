<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->string('preferred_reminder_channel', 20)->default('whatsapp');
            $table->boolean('allow_reminders')->default(true);
            $table->timestamp('last_reminder_at')->nullable();
            $table->text('reminder_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'name']);
            $table->index(['business_id', 'phone']);
            $table->index(['business_id', 'email']);
            $table->index(['business_id', 'preferred_reminder_channel'], 'customers_business_channel_index');
            $table->index(['business_id', 'allow_reminders'], 'customers_business_allow_reminders_index');
            $table->index(['business_id', 'last_reminder_at'], 'customers_business_last_reminder_index');
            $table->unique(['id', 'business_id'], 'customers_id_business_id_unique');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->unsignedBigInteger('customer_id')->nullable()->after('sale_sector_id');
            $table->string('payment_status', 20)->default('paid')->after('payment_method');
            $table->decimal('paid_amount', 12, 2)->default(0)->after('change_amount');
            $table->decimal('pending_amount', 12, 2)->default(0)->after('paid_amount');

            $table->index(['business_id', 'customer_id'], 'sales_business_customer_index');
            $table->index(['business_id', 'payment_status'], 'sales_business_payment_status_index');
            $table->index(['business_id', 'pending_amount'], 'sales_business_pending_amount_index');

            $table->foreign(['customer_id', 'business_id'], 'sales_customer_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('customers')
                ->restrictOnDelete();
        });

        Schema::create('customer_account_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->string('type', 20);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'customer_id', 'created_at'], 'customer_account_movements_business_customer_created_index');
            $table->index(['business_id', 'customer_id', 'type', 'created_at'], 'customer_account_movements_business_customer_type_created_index');
            $table->index(['business_id', 'sale_id'], 'customer_account_movements_business_sale_index');
            $table->index(['business_id', 'created_by'], 'customer_account_movements_business_creator_index');

            $table->foreign(['customer_id', 'business_id'], 'customer_account_movements_customer_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('customers')
                ->restrictOnDelete();

            $table->foreign(['sale_id', 'business_id'], 'customer_account_movements_sale_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('sales')
                ->restrictOnDelete();

            $table->foreign(['created_by', 'business_id'], 'customer_account_movements_created_by_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('users')
                ->restrictOnDelete();
        });

        Schema::create('customer_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->string('subject')->nullable();
            $table->text('message_snapshot')->nullable();
            $table->text('target')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'customer_id', 'sent_at'], 'customer_reminders_business_customer_sent_index');
            $table->index(['business_id', 'channel', 'sent_at'], 'customer_reminders_business_channel_sent_index');
            $table->index(['business_id', 'status', 'sent_at'], 'customer_reminders_business_status_sent_index');

            $table->foreign(['customer_id', 'business_id'], 'customer_reminders_customer_id_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('customers')
                ->restrictOnDelete();

            $table->foreign(['sent_by', 'business_id'], 'customer_reminders_sent_by_business_id_foreign')
                ->references(['id', 'business_id'])
                ->on('users')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_reminders');
        Schema::dropIfExists('customer_account_movements');

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropForeign('sales_customer_id_business_id_foreign');
            $table->dropIndex('sales_business_pending_amount_index');
            $table->dropIndex('sales_business_payment_status_index');
            $table->dropIndex('sales_business_customer_index');
            $table->dropColumn([
                'customer_id',
                'payment_status',
                'paid_amount',
                'pending_amount',
            ]);
        });

        Schema::dropIfExists('customers');
    }
};
