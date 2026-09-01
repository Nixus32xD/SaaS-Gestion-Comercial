<?php

use App\Models\Business;
use App\Models\Sale;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('sales pagination uses Spanish labels', function () {
    $business = Business::factory()->create();
    $admin = User::factory()->businessAdmin($business->id)->create();

    foreach (range(1, 16) as $number) {
        Sale::query()->create([
            'business_id' => $business->id,
            'user_id' => $admin->id,
            'sale_number' => sprintf('S-PAGE-%06d', $number),
            'subtotal' => 100,
            'discount' => 0,
            'total' => 100,
            'sold_at' => now()->subMinutes($number),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('sales.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Sales/Index')
            ->where('sales.links.0.label', '&laquo; Anterior')
            ->where('sales.links.3.label', 'Siguiente &raquo;')
        );
});
