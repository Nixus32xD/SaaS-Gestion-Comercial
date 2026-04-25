<?php

use App\Mail\CustomerDebtReminderMail;
use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerReminder;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('whatsapp reminder route generates log entry and redirects to wa.me', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $customer = Customer::factory()->create([
        'business_id' => $business->id,
        'phone' => '5491122334455',
        'email' => 'cliente@example.com',
    ]);
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Harina',
        'slug' => 'harina-recordatorio-whatsapp',
        'unit_type' => 'unit',
        'sale_price' => 900,
        'cost_price' => 500,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)->post('/sales', [
        'customer_id' => $customer->id,
        'payment_status' => 'pending',
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 900,
        ]],
    ])->assertRedirect();

    $response = $this
        ->actingAs($admin)
        ->get("/customers/{$customer->id}/reminders/whatsapp");

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://wa.me/');
    expect(CustomerReminder::query()->where('channel', CustomerReminder::CHANNEL_WHATSAPP)->count())->toBe(1);
    expect(CustomerReminder::query()->where('channel', CustomerReminder::CHANNEL_WHATSAPP)->first()?->status)
        ->toBe(CustomerReminder::STATUS_GENERATED);
});

test('email reminder sends mail and stores reminder log', function () {
    Mail::fake();

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $customer = Customer::factory()->create([
        'business_id' => $business->id,
        'phone' => '5491166677788',
        'email' => 'cliente@example.com',
        'preferred_reminder_channel' => 'email',
    ]);
    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Cafe',
        'slug' => 'cafe-recordatorio-email',
        'unit_type' => 'unit',
        'sale_price' => 1500,
        'cost_price' => 900,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)->post('/sales', [
        'customer_id' => $customer->id,
        'payment_status' => 'pending',
        'items' => [[
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1500,
        ]],
    ])->assertRedirect();

    $this
        ->actingAs($admin)
        ->from("/customers/{$customer->id}")
        ->post("/customers/{$customer->id}/reminders/email")
        ->assertRedirect("/customers/{$customer->id}");

    Mail::assertSent(CustomerDebtReminderMail::class, fn (CustomerDebtReminderMail $mail) => $mail->hasTo('cliente@example.com'));
    expect(CustomerReminder::query()->where('channel', CustomerReminder::CHANNEL_EMAIL)->count())->toBe(1);
    expect(CustomerReminder::query()->where('channel', CustomerReminder::CHANNEL_EMAIL)->first()?->status)
        ->toBe(CustomerReminder::STATUS_SENT);
});
