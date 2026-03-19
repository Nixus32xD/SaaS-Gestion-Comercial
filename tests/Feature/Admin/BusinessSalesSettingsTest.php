<?php

use App\Models\Business;
use App\Models\BusinessFeature;
use App\Models\BusinessPaymentDestination;
use App\Models\BusinessSaleSector;
use App\Models\User;

test('superadmin can configure advanced sale settings for a business', function () {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.sales-settings.update', $business), [
            'advanced_sale_settings_enabled' => true,
            'sale_sectors' => [
                ['name' => 'Almacen', 'description' => 'Mostrador principal', 'is_active' => true],
                ['name' => 'Viviendas', 'description' => 'Ventas por unidad', 'is_active' => true],
            ],
            'payment_destinations' => [
                [
                    'name' => 'Mercado Pago Almacen',
                    'account_holder' => 'Comercio SA',
                    'reference' => 'alias.almacen',
                    'account_number' => 'CVU-001',
                    'is_active' => true,
                ],
                [
                    'name' => 'Banco Viviendas',
                    'account_holder' => 'Comercio SA',
                    'reference' => 'CBU viviendas',
                    'account_number' => 'CBU-002',
                    'is_active' => true,
                ],
            ],
        ])
        ->assertRedirect(route('admin.businesses.edit', $business));

    expect($business->fresh()->hasAdvancedSaleSettings())->toBeTrue();

    expect(BusinessSaleSector::query()->where('business_id', $business->id)->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Almacen', 'Viviendas']);

    expect(BusinessPaymentDestination::query()->where('business_id', $business->id)->orderBy('sort_order')->pluck('name')->all())
        ->toBe(['Mercado Pago Almacen', 'Banco Viviendas']);
});
