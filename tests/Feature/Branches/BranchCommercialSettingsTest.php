<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\BusinessPaymentDestination;
use App\Models\BusinessSaleSector;
use App\Models\Customer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('superadmin configures advanced sales independently for each branch of a business', function () {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();
    $branchA = $business->defaultBranch;
    $branchB = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Norte',
        'code' => 'norte',
        'is_active' => true,
        'is_default' => false,
    ]);

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.branches.commercial-settings.update', [$business, $branchB]), [
            'advanced_sale_settings_enabled' => true,
            'sale_sectors' => [
                ['name' => 'Mostrador Norte', 'description' => '', 'is_active' => true],
            ],
            'payment_destinations' => [
                ['name' => 'Caja Norte', 'account_holder' => '', 'reference' => 'caja-norte', 'account_number' => '', 'is_active' => true],
            ],
        ])
        ->assertRedirect(route('admin.businesses.edit', $business));

    expect($branchB->commercialSetting()->value('advanced_sale_settings_enabled'))->toBeTrue()
        ->and($branchA->commercialSetting()->value('advanced_sale_settings_enabled'))->toBeNull()
        ->and(BusinessSaleSector::query()->where('branch_id', $branchB->id)->pluck('name')->all())->toBe(['Mostrador Norte'])
        ->and(BusinessPaymentDestination::query()->where('branch_id', $branchB->id)->pluck('name')->all())->toBe(['Caja Norte'])
        ->and(BusinessSaleSector::query()->where('branch_id', $branchA->id)->count())->toBe(0);
});

test('sales creation only exposes the commercial configuration of the active branch', function () {
    $this->withoutVite();

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Sur',
        'code' => 'sur',
        'is_active' => true,
        'is_default' => false,
    ]);

    $branch->commercialSetting()->create([
        'business_id' => $business->id,
        'advanced_sale_settings_enabled' => true,
    ]);
    BusinessSaleSector::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'name' => 'Taller Sur',
        'is_active' => true,
    ]);
    BusinessPaymentDestination::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branch->id,
        'name' => 'Caja Sur',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->withSession(['branch_id' => $branch->id])
        ->get(route('sales.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Create')
            ->where('advanced_sale_settings.enabled', true)
            ->where('advanced_sale_settings.sale_sectors.0.name', 'Taller Sur')
            ->where('advanced_sale_settings.payment_destinations.0.name', 'Caja Sur')
        );
});

test('customers remain shared by the business when changing branches', function () {
    $this->withoutVite();

    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Oeste',
        'code' => 'oeste',
        'is_active' => true,
        'is_default' => false,
    ]);
    $customer = Customer::factory()->create([
        'business_id' => $business->id,
        'name' => 'Cliente Compartido',
    ]);

    $this->actingAs($admin)
        ->withSession(['branch_id' => $branch->id])
        ->get(route('customers.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Customers/Index')
            ->where('customers.data.0.id', $customer->id)
            ->where('customers.data.0.name', 'Cliente Compartido')
        );
});
