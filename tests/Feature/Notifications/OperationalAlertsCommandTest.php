<?php

use App\Jobs\SendBusinessOperationalAlertsJob;
use App\Mail\BusinessOperationalAlertsMail;
use App\Models\Business;
use App\Models\BusinessNotificationDispatch;
use App\Models\BusinessNotificationSetting;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

test('operational alerts command sends one digest per resolved recipient and avoids duplicates within window', function () {
    $this->travelTo(now()->setTime(10, 0));

    Queue::fake();

    $business = Business::factory()->create([
        'name' => 'Almacen Centro',
        'email' => 'dueno@almacen.test',
    ]);

    $admin = User::factory()->businessAdmin($business->id)->create([
        'email' => 'admin@almacen.test',
    ]);

    BusinessNotificationSetting::query()->create([
        'business_id' => $business->id,
        'notifications_enabled' => true,
        'send_to_business_email' => true,
        'send_to_admin_users' => true,
        'extra_recipients' => ['compras@almacen.test'],
        'low_stock_enabled' => true,
        'expiration_enabled' => true,
        'minimum_hours_between_alerts' => 12,
        'notification_window_start_hour' => 9,
        'notification_window_end_hour' => 18,
    ]);

    Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Yerba',
        'slug' => 'yerba-alerta',
        'unit_type' => 'unit',
        'sale_price' => 2000,
        'cost_price' => 1200,
        'stock' => 2,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $expiringProduct = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Yogur',
        'slug' => 'yogur-alerta',
        'unit_type' => 'unit',
        'sale_price' => 1500,
        'cost_price' => 900,
        'stock' => 4,
        'min_stock' => 1,
        'expiry_alert_days' => 7,
        'is_active' => true,
    ]);

    ProductBatch::query()->create([
        'business_id' => $business->id,
        'product_id' => $expiringProduct->id,
        'batch_code' => 'YOG-01',
        'expires_at' => now()->addDays(3)->toDateString(),
        'quantity' => 4,
        'unit_cost' => 900,
    ]);

    $otherBusiness = Business::factory()->create([
        'email' => 'otro@cliente.test',
    ]);

    User::factory()->businessAdmin($otherBusiness->id)->create([
        'email' => 'admin-otro@cliente.test',
    ]);

    $this->artisan('notifications:send-operational-alerts')
        ->assertExitCode(0);

    Queue::assertPushed(SendBusinessOperationalAlertsJob::class, 1);

    expect(BusinessNotificationDispatch::query()->forBusiness($business->id)->count())->toBe(1);
    expect(BusinessNotificationDispatch::query()->forBusiness($business->id)->first()?->status)->toBe(BusinessNotificationDispatch::STATUS_QUEUED);
    expect(BusinessNotificationDispatch::query()->forBusiness($otherBusiness->id)->count())->toBe(0);

    $this->artisan('notifications:send-operational-alerts')
        ->assertExitCode(0);

    Queue::assertPushed(SendBusinessOperationalAlertsJob::class, 1);
    expect(BusinessNotificationDispatch::query()->forBusiness($business->id)->count())->toBe(1);

    $this->travelBack();
});

test('operational alerts command skips sends outside configured business hours unless forced', function () {
    $this->travelTo(now()->setTime(22, 0));

    Queue::fake();

    $business = Business::factory()->create([
        'email' => 'dueno@fuera-horario.test',
    ]);

    BusinessNotificationSetting::query()->create([
        'business_id' => $business->id,
        'notifications_enabled' => true,
        'send_to_business_email' => true,
        'send_to_admin_users' => false,
        'extra_recipients' => [],
        'low_stock_enabled' => true,
        'expiration_enabled' => false,
        'minimum_hours_between_alerts' => 12,
        'notification_window_start_hour' => 9,
        'notification_window_end_hour' => 18,
    ]);

    Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Arroz',
        'slug' => 'arroz-fuera-horario',
        'unit_type' => 'unit',
        'sale_price' => 1000,
        'cost_price' => 700,
        'stock' => 1,
        'min_stock' => 5,
        'is_active' => true,
    ]);

    $this->artisan('notifications:send-operational-alerts')
        ->assertExitCode(0);

    Queue::assertNothingPushed();
    expect(BusinessNotificationDispatch::query()->forBusiness($business->id)->count())->toBe(0);

    $this->artisan('notifications:send-operational-alerts --force')
        ->assertExitCode(0);

    Queue::assertPushed(SendBusinessOperationalAlertsJob::class, 1);
    expect(BusinessNotificationDispatch::query()->forBusiness($business->id)->count())->toBe(1);
    expect(BusinessNotificationDispatch::query()->forBusiness($business->id)->first()?->status)->toBe(BusinessNotificationDispatch::STATUS_QUEUED);

    $this->travelBack();
});

test('operational alerts command skips businesses with notifications disabled', function () {
    $this->travelTo(now()->setTime(10, 0));

    Queue::fake();

    $business = Business::factory()->create([
        'email' => 'pausado@cliente.test',
    ]);

    BusinessNotificationSetting::query()->create([
        'business_id' => $business->id,
        'notifications_enabled' => false,
        'send_to_business_email' => true,
        'send_to_admin_users' => false,
        'extra_recipients' => [],
        'low_stock_enabled' => true,
        'expiration_enabled' => false,
        'minimum_hours_between_alerts' => 12,
        'notification_window_start_hour' => 9,
        'notification_window_end_hour' => 18,
    ]);

    Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Fideos',
        'slug' => 'fideos-pausado',
        'unit_type' => 'unit',
        'sale_price' => 1200,
        'cost_price' => 800,
        'stock' => 1,
        'min_stock' => 4,
        'is_active' => true,
    ]);

    $this->artisan('notifications:send-operational-alerts')
        ->assertExitCode(0);

    Queue::assertNothingPushed();
    expect(BusinessNotificationDispatch::query()->forBusiness($business->id)->count())->toBe(0);

    $this->travelBack();
});

test('operational alerts command supports notification windows that cross midnight', function () {
    $this->travelTo(now()->setTime(23, 0));

    Queue::fake();

    $business = Business::factory()->create([
        'email' => 'noche@cliente.test',
    ]);

    BusinessNotificationSetting::query()->create([
        'business_id' => $business->id,
        'notifications_enabled' => true,
        'send_to_business_email' => true,
        'send_to_admin_users' => false,
        'extra_recipients' => [],
        'low_stock_enabled' => true,
        'expiration_enabled' => false,
        'minimum_hours_between_alerts' => 12,
        'notification_window_start_hour' => 22,
        'notification_window_end_hour' => 6,
    ]);

    Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Cafe',
        'slug' => 'cafe-turno-noche',
        'unit_type' => 'unit',
        'sale_price' => 1800,
        'cost_price' => 1000,
        'stock' => 1,
        'min_stock' => 4,
        'is_active' => true,
    ]);

    $this->artisan('notifications:send-operational-alerts')
        ->assertExitCode(0);

    Queue::assertPushed(SendBusinessOperationalAlertsJob::class, 1);
    expect(BusinessNotificationDispatch::query()->forBusiness($business->id)->count())->toBe(1);

    $this->travelBack();
});
