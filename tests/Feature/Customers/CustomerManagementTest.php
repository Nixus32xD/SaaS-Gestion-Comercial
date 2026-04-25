<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\User;

test('business users can create customers and only see their own business records', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $admin = User::factory()->businessAdmin($businessA->id)->create();

    Customer::factory()->create([
        'business_id' => $businessB->id,
        'name' => 'Cliente Ajeno',
    ]);

    $this
        ->actingAs($admin)
        ->post('/customers', [
            'name' => 'Juan Perez',
            'phone' => '5491122334455',
            'email' => 'juan@example.com',
            'address' => 'Calle 123',
            'notes' => 'Compra seguido',
            'preferred_reminder_channel' => 'whatsapp',
            'allow_reminders' => true,
            'reminder_notes' => 'Escribir por la tarde',
        ])
        ->assertRedirect();

    $customer = Customer::query()->where('name', 'Juan Perez')->first();

    expect($customer)->not()->toBeNull();
    expect($customer?->business_id)->toBe($businessA->id);

    $this->actingAs($admin)->get('/customers')
        ->assertOk()
        ->assertSee('Juan Perez')
        ->assertDontSee('Cliente Ajeno');
});

test('business users cannot access customer detail routes from another business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $adminA = User::factory()->businessAdmin($businessA->id)->create();

    $foreignCustomer = Customer::factory()->create([
        'business_id' => $businessB->id,
    ]);

    $this->actingAs($adminA)->get("/customers/{$foreignCustomer->id}")->assertForbidden();
    $this->actingAs($adminA)->get("/customers/{$foreignCustomer->id}/edit")->assertForbidden();
});

test('customer creation can return to sales flow with the new customer selected', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $response = $this
        ->actingAs($admin)
        ->post('/customers', [
            'name' => 'Cliente desde venta',
            'phone' => '5491111111111',
            'email' => 'venta@example.com',
            'address' => 'Mostrador',
            'notes' => 'Alta durante una venta',
            'preferred_reminder_channel' => 'whatsapp',
            'allow_reminders' => true,
            'reminder_notes' => '',
            'return_to' => 'sales.create',
        ]);

    $customer = Customer::query()->where('name', 'Cliente desde venta')->firstOrFail();

    $response->assertRedirect(route('sales.create', ['customer_id' => $customer->id]));
});
