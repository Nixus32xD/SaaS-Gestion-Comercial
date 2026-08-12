<?php

use App\Models\Business;
use App\Models\BusinessQuickSaleOption;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('sale create page exposes generic and business quick sale options', function () {
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    BusinessQuickSaleOption::query()->create([
        'business_id' => $business->id,
        'name' => 'Aceite por litro',
        'description' => 'Lubricante fraccionado.',
        'default_amount' => 3500,
        'vat_treatment' => 'gravado',
        'vat_rate' => 21,
        'position' => 1,
        'is_active' => true,
    ]);

    BusinessQuickSaleOption::query()->create([
        'business_id' => $otherBusiness->id,
        'name' => 'Opcion de otro comercio',
        'vat_treatment' => 'gravado',
        'vat_rate' => 21,
        'position' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('sales.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Create')
            ->where('quick_sale_options.0.name', 'Producto sin stock')
            ->where('quick_sale_options.4.name', 'Aceite por litro')
            ->where('quick_sale_options.4.default_amount', fn ($value) => (float) $value === 3500.0)
            ->where('can_manage_quick_sale_options', true)
        );
});

test('business admin can manage quick sale options for own business', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    $this->actingAs($admin)
        ->from(route('sales.create'))
        ->post(route('sales.quick-options.store'), [
            'name' => 'Tornilleria suelta',
            'description' => 'Venta por unidad suelta.',
            'default_amount' => 250,
            'vat_treatment' => 'gravado',
            'vat_rate' => 10.5,
            'is_active' => true,
        ])
        ->assertRedirect(route('sales.create'));

    $option = BusinessQuickSaleOption::query()->firstOrFail();

    expect($option->business_id)->toBe($business->id);
    expect($option->name)->toBe('Tornilleria suelta');
    expect((float) $option->default_amount)->toBe(250.0);
    expect((float) $option->vat_rate)->toBe(10.5);

    $this->actingAs($admin)
        ->from(route('sales.create'))
        ->delete(route('sales.quick-options.destroy', $option))
        ->assertRedirect(route('sales.create'));

    expect(BusinessQuickSaleOption::query()->count())->toBe(0);
});

test('staff cannot manage quick sale options and businesses are isolated', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    $staff = User::factory()->staff($businessA->id)->create();
    $adminB = User::factory()->businessAdmin($businessB->id)->create();

    $optionA = BusinessQuickSaleOption::query()->create([
        'business_id' => $businessA->id,
        'name' => 'Mano de obra',
        'vat_treatment' => 'gravado',
        'vat_rate' => 21,
        'position' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($staff)
        ->post(route('sales.quick-options.store'), [
            'name' => 'Servicio express',
            'vat_treatment' => 'gravado',
            'vat_rate' => 21,
        ])
        ->assertForbidden();

    $this->actingAs($adminB)
        ->delete(route('sales.quick-options.destroy', $optionA))
        ->assertNotFound();

    expect($optionA->fresh())->not->toBeNull();
});
