<?php

use App\Models\Business;
use App\Models\BusinessNotificationSetting;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('business admin can view notification settings for current business', function () {
    $this->withoutVite();

    $business = Business::factory()->create([
        'email' => 'negocio@cliente.test',
    ]);

    $admin = User::factory()->businessAdmin($business->id)->create([
        'email' => 'admin@cliente.test',
    ]);

    BusinessNotificationSetting::query()->create([
        'business_id' => $business->id,
        'notifications_enabled' => true,
        'send_to_business_email' => true,
        'send_to_admin_users' => true,
        'extra_recipients' => ['compras@cliente.test'],
        'low_stock_enabled' => true,
        'expiration_enabled' => false,
        'minimum_hours_between_alerts' => 8,
        'notification_window_start_hour' => 9,
        'notification_window_end_hour' => 18,
    ]);

    $this->actingAs($admin)->get('/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Notifications/Edit')
            ->where('settings.notifications_enabled', true)
            ->where('settings.minimum_hours_between_alerts', 8)
            ->where('settings.expiration_enabled', false)
            ->where('settings.maintenance_due_enabled', true)
            ->where('settings.notification_window_start_hour', 9)
            ->where('settings.notification_window_end_hour', 18)
            ->has('recipient_preview', 3)
        );
});

test('business admin can update notification settings', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $this->actingAs($admin)->put('/notifications', [
        'notifications_enabled' => false,
        'send_to_business_email' => true,
        'send_to_admin_users' => false,
        'low_stock_enabled' => true,
        'expiration_enabled' => true,
        'maintenance_due_enabled' => false,
        'minimum_hours_between_alerts' => 24,
        'notification_window_start_hour' => 10,
        'notification_window_end_hour' => 20,
        'extra_recipients_text' => "compras@cliente.test\nstock@cliente.test",
    ])->assertRedirect();

    $settings = BusinessNotificationSetting::query()->where('business_id', $business->id)->first();

    expect($settings)->not()->toBeNull();
    expect($settings?->notifications_enabled)->toBeFalse();
    expect($settings?->send_to_admin_users)->toBeFalse();
    expect($settings?->minimum_hours_between_alerts)->toBe(24);
    expect($settings?->maintenance_due_enabled)->toBeFalse();
    expect($settings?->notification_window_start_hour)->toBe(10);
    expect($settings?->notification_window_end_hour)->toBe(20);
    expect($settings?->extra_recipients)->toBe([
        'compras@cliente.test',
        'stock@cliente.test',
    ]);
});

test('business admin can save a notification window that crosses midnight', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $this->actingAs($admin)->put('/notifications', [
        'notifications_enabled' => true,
        'send_to_business_email' => true,
        'send_to_admin_users' => false,
        'low_stock_enabled' => true,
        'expiration_enabled' => true,
        'maintenance_due_enabled' => true,
        'minimum_hours_between_alerts' => 12,
        'notification_window_start_hour' => 22,
        'notification_window_end_hour' => 6,
        'extra_recipients_text' => '',
    ])->assertRedirect();

    $settings = BusinessNotificationSetting::query()->where('business_id', $business->id)->first();

    expect($settings)->not()->toBeNull();
    expect($settings?->notification_window_start_hour)->toBe(22);
    expect($settings?->notification_window_end_hour)->toBe(6);
});

test('staff cannot access notification settings page', function () {
    $business = Business::factory()->create();
    $staff = User::factory()->staff($business->id)->create();

    $this->actingAs($staff)->get('/notifications')
        ->assertForbidden();
});
