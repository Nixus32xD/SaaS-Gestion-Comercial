<?php

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerAccountMovement;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

test('customer account index only shows customers from the active business with positive balance by default', function () {
    $this->withoutVite();

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $customerWithDebt = Customer::factory()->create([
        'business_id' => $business->id,
        'name' => 'Cliente con deuda',
    ]);

    $customerCleared = Customer::factory()->create([
        'business_id' => $business->id,
        'name' => 'Cliente saldado',
    ]);

    $foreignCustomer = Customer::factory()->create([
        'business_id' => $otherBusiness->id,
        'name' => 'Cliente ajeno',
    ]);

    CustomerAccountMovement::query()->create([
        'business_id' => $business->id,
        'customer_id' => $customerWithDebt->id,
        'type' => CustomerAccountMovement::TYPE_DEBT,
        'amount' => 1250,
        'balance_after' => 1250,
        'description' => 'Venta fiada',
        'created_at' => CarbonImmutable::parse('2026-03-26 10:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-26 10:00:00'),
    ]);

    CustomerAccountMovement::query()->create([
        'business_id' => $business->id,
        'customer_id' => $customerCleared->id,
        'type' => CustomerAccountMovement::TYPE_DEBT,
        'amount' => 500,
        'balance_after' => 500,
        'description' => 'Venta fiada',
        'created_at' => CarbonImmutable::parse('2026-03-25 09:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-25 09:00:00'),
    ]);

    CustomerAccountMovement::query()->create([
        'business_id' => $business->id,
        'customer_id' => $customerCleared->id,
        'type' => CustomerAccountMovement::TYPE_PAYMENT,
        'amount' => 500,
        'balance_after' => 0,
        'description' => 'Pago total',
        'created_at' => CarbonImmutable::parse('2026-03-27 11:30:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-27 11:30:00'),
    ]);

    CustomerAccountMovement::query()->create([
        'business_id' => $otherBusiness->id,
        'customer_id' => $foreignCustomer->id,
        'type' => CustomerAccountMovement::TYPE_DEBT,
        'amount' => 900,
        'balance_after' => 900,
        'description' => 'Venta fiada',
        'created_at' => CarbonImmutable::parse('2026-03-24 15:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-24 15:00:00'),
    ]);

    $this
        ->actingAs($admin)
        ->get('/customer-accounts')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerAccounts/Index')
            ->where('filters.only_with_balance', true)
            ->where('summary.customers_count', 1)
            ->where('summary.total_debt', fn ($value) => (float) $value === 1250.0)
            ->has('customers.data', 1)
            ->where('customers.data.0.name', 'Cliente con deuda')
            ->where('customers.data.0.current_balance', fn ($value) => (float) $value === 1250.0)
        );
});

test('customer account index supports date range and last activity ordering', function () {
    $this->withoutVite();

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $olderCustomer = Customer::factory()->create([
        'business_id' => $business->id,
        'name' => 'Cliente antiguo',
    ]);

    $recentPaidCustomer = Customer::factory()->create([
        'business_id' => $business->id,
        'name' => 'Cliente reciente sin saldo',
    ]);

    $recentDueCustomer = Customer::factory()->create([
        'business_id' => $business->id,
        'name' => 'Cliente reciente con saldo',
    ]);

    CustomerAccountMovement::query()->create([
        'business_id' => $business->id,
        'customer_id' => $olderCustomer->id,
        'type' => CustomerAccountMovement::TYPE_DEBT,
        'amount' => 2100,
        'balance_after' => 2100,
        'description' => 'Deuda vieja',
        'created_at' => CarbonImmutable::parse('2026-03-20 10:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-20 10:00:00'),
    ]);

    CustomerAccountMovement::query()->create([
        'business_id' => $business->id,
        'customer_id' => $recentPaidCustomer->id,
        'type' => CustomerAccountMovement::TYPE_DEBT,
        'amount' => 700,
        'balance_after' => 700,
        'description' => 'Deuda reciente',
        'created_at' => CarbonImmutable::parse('2026-03-27 08:30:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-27 08:30:00'),
    ]);

    CustomerAccountMovement::query()->create([
        'business_id' => $business->id,
        'customer_id' => $recentPaidCustomer->id,
        'type' => CustomerAccountMovement::TYPE_PAYMENT,
        'amount' => 700,
        'balance_after' => 0,
        'description' => 'Pago total',
        'created_at' => CarbonImmutable::parse('2026-03-28 09:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-28 09:00:00'),
    ]);

    CustomerAccountMovement::query()->create([
        'business_id' => $business->id,
        'customer_id' => $recentDueCustomer->id,
        'type' => CustomerAccountMovement::TYPE_DEBT,
        'amount' => 950,
        'balance_after' => 950,
        'description' => 'Deuda activa',
        'created_at' => CarbonImmutable::parse('2026-03-27 12:00:00'),
        'updated_at' => CarbonImmutable::parse('2026-03-27 12:00:00'),
    ]);

    $this
        ->actingAs($admin)
        ->get('/customer-accounts?only_with_balance=0&date_from=2026-03-27&date_to=2026-03-28&sort=last_activity')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CustomerAccounts/Index')
            ->where('filters.only_with_balance', false)
            ->where('filters.date_from', '2026-03-27')
            ->where('filters.date_to', '2026-03-28')
            ->where('filters.sort', 'last_activity')
            ->where('summary.customers_count', 2)
            ->where('summary.total_debt', fn ($value) => (float) $value === 950.0)
            ->has('customers.data', 2)
            ->where('customers.data.0.name', 'Cliente reciente sin saldo')
            ->where('customers.data.1.name', 'Cliente reciente con saldo')
        );
});

test('customer account detail route redirects to the reusable customer detail and blocks foreign customers', function () {
    $this->withoutVite();

    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $customer = Customer::factory()->create([
        'business_id' => $business->id,
    ]);

    $foreignCustomer = Customer::factory()->create([
        'business_id' => $otherBusiness->id,
    ]);

    $response = $this
        ->actingAs($admin)
        ->get("/customer-accounts/{$customer->id}");

    $response->assertRedirect(route('customers.show', $customer).'#current-account');

    $this
        ->actingAs($admin)
        ->get("/customer-accounts/{$foreignCustomer->id}")
        ->assertForbidden();
});
