<?php

use App\Jobs\SendBusinessMaintenanceReminderJob;
use App\Models\Business;
use App\Models\BusinessNotificationDispatch;
use App\Models\BusinessNotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('maintenance reminder command queues one reminder when due in 7 days and avoids duplicates', function () {
    $this->travelTo(now()->setDate(2026, 4, 23)->setTime(10, 0));

    Queue::fake();

    $business = Business::factory()->create([
        'name' => 'Ferreteria Norte',
        'email' => 'dueno@ferreteria.test',
        'maintenance_plan_code' => 'basico',
        'maintenance_amount' => 25000,
        'maintenance_started_at' => '2026-03-30',
        'maintenance_ends_at' => '2026-04-30',
        'subscription_grace_days' => 7,
    ]);

    User::factory()->businessAdmin($business->id)->create([
        'email' => 'admin@ferreteria.test',
    ]);

    BusinessNotificationSetting::query()->create([
        'business_id' => $business->id,
        'notifications_enabled' => true,
        'send_to_business_email' => true,
        'send_to_admin_users' => true,
        'extra_recipients' => ['cobros@ferreteria.test'],
        'low_stock_enabled' => true,
        'expiration_enabled' => true,
        'maintenance_due_enabled' => true,
        'minimum_hours_between_alerts' => 12,
        'notification_window_start_hour' => 9,
        'notification_window_end_hour' => 18,
    ]);

    $this->artisan('notifications:send-maintenance-reminders')
        ->assertExitCode(0);

    Queue::assertPushed(SendBusinessMaintenanceReminderJob::class, 1);

    $dispatch = BusinessNotificationDispatch::query()->forBusiness($business->id)->first();

    expect($dispatch)->not()->toBeNull();
    expect($dispatch?->notification_type)->toBe(BusinessNotificationDispatch::TYPE_MAINTENANCE_DUE_REMINDER);
    expect($dispatch?->status)->toBe(BusinessNotificationDispatch::STATUS_QUEUED);

    $this->artisan('notifications:send-maintenance-reminders')
        ->assertExitCode(0);

    Queue::assertPushed(SendBusinessMaintenanceReminderJob::class, 1);
    expect(BusinessNotificationDispatch::query()->forBusiness($business->id)->count())->toBe(1);

    $this->travelBack();
});

test('maintenance reminder command skips businesses outside the 7 day window or with reminders disabled', function () {
    $this->travelTo(now()->setDate(2026, 4, 20)->setTime(10, 0));

    Queue::fake();

    $businessDueLater = Business::factory()->create([
        'maintenance_plan_code' => 'basico',
        'maintenance_amount' => 25000,
        'maintenance_started_at' => '2026-03-30',
        'maintenance_ends_at' => '2026-04-30',
        'subscription_grace_days' => 7,
    ]);

    BusinessNotificationSetting::query()->create([
        'business_id' => $businessDueLater->id,
        'notifications_enabled' => true,
        'send_to_business_email' => true,
        'send_to_admin_users' => false,
        'extra_recipients' => [],
        'low_stock_enabled' => true,
        'expiration_enabled' => true,
        'maintenance_due_enabled' => true,
        'minimum_hours_between_alerts' => 12,
        'notification_window_start_hour' => 9,
        'notification_window_end_hour' => 18,
    ]);

    $businessDisabled = Business::factory()->create([
        'maintenance_plan_code' => 'operativo',
        'maintenance_amount' => 45000,
        'maintenance_started_at' => '2026-03-27',
        'maintenance_ends_at' => '2026-04-27',
        'subscription_grace_days' => 7,
        'email' => 'pagos@deshabilitado.test',
    ]);

    BusinessNotificationSetting::query()->create([
        'business_id' => $businessDisabled->id,
        'notifications_enabled' => true,
        'send_to_business_email' => true,
        'send_to_admin_users' => false,
        'extra_recipients' => [],
        'low_stock_enabled' => true,
        'expiration_enabled' => true,
        'maintenance_due_enabled' => false,
        'minimum_hours_between_alerts' => 12,
        'notification_window_start_hour' => 9,
        'notification_window_end_hour' => 18,
    ]);

    $this->artisan('notifications:send-maintenance-reminders')
        ->assertExitCode(0);

    Queue::assertNothingPushed();
    expect(BusinessNotificationDispatch::query()->count())->toBe(0);

    $this->travelBack();
});

test('maintenance reminder command still queues reminder when operational notifications are paused', function () {
    $this->travelTo(now()->setDate(2026, 4, 23)->setTime(10, 0));

    Queue::fake();

    $business = Business::factory()->create([
        'name' => 'Lubricentro Centro',
        'email' => 'pagos@lubricentro.test',
        'maintenance_plan_code' => 'operativo',
        'maintenance_amount' => 45000,
        'maintenance_started_at' => '2026-03-27',
        'maintenance_ends_at' => '2026-04-30',
        'subscription_grace_days' => 7,
    ]);

    BusinessNotificationSetting::query()->create([
        'business_id' => $business->id,
        'notifications_enabled' => false,
        'send_to_business_email' => true,
        'send_to_admin_users' => false,
        'extra_recipients' => [],
        'low_stock_enabled' => true,
        'expiration_enabled' => true,
        'maintenance_due_enabled' => true,
        'minimum_hours_between_alerts' => 12,
        'notification_window_start_hour' => 9,
        'notification_window_end_hour' => 18,
    ]);

    $this->artisan('notifications:send-maintenance-reminders')
        ->assertExitCode(0);

    Queue::assertPushed(SendBusinessMaintenanceReminderJob::class, 1);

    $dispatch = BusinessNotificationDispatch::query()->forBusiness($business->id)->first();

    expect($dispatch)->not()->toBeNull();
    expect($dispatch?->notification_type)->toBe(BusinessNotificationDispatch::TYPE_MAINTENANCE_DUE_REMINDER);
    expect($dispatch?->status)->toBe(BusinessNotificationDispatch::STATUS_QUEUED);

    $this->travelBack();
});
