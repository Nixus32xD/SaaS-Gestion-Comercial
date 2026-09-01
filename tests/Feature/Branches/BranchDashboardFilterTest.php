<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('dashboard can be scoped to the active branch while preserving consolidated mode', function () {
    $business = Business::factory()->create();
    $branchA = $business->defaultBranch;
    $branchB = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Centro',
        'code' => 'centro',
        'is_active' => true,
        'is_default' => false,
    ]);
    $user = User::factory()->businessAdmin($business->id)->create();
    $supplier = Supplier::query()->create(['business_id' => $business->id, 'name' => 'Proveedor dashboard']);

    Sale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branchA->id,
        'user_id' => $user->id,
        'sale_number' => 'S-DASH-A',
        'subtotal' => 100,
        'discount' => 0,
        'total' => 100,
        'sold_at' => now(),
    ]);
    Sale::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branchB->id,
        'user_id' => $user->id,
        'sale_number' => 'S-DASH-B',
        'subtotal' => 250,
        'discount' => 0,
        'total' => 250,
        'sold_at' => now(),
    ]);
    Purchase::query()->create([
        'business_id' => $business->id,
        'branch_id' => $branchB->id,
        'user_id' => $user->id,
        'supplier_id' => $supplier->id,
        'purchase_number' => 'P-DASH-B',
        'subtotal' => 75,
        'total' => 75,
        'purchased_at' => now(),
    ]);

    $this->actingAs($user)
        ->withSession(['business_id' => $business->id, 'branch_id' => $branchB->id])
        ->get(route('dashboard', ['branch_scope' => 'current']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('business.has_multiple_branches', true)
            ->where('branch_filter.scope', 'current')
            ->where('branch_filter.current_branch_name', 'Sucursal Centro')
            ->where('summary.today_sales', 250)
            ->where('historical_summary.periods.0.purchases_total', 75)
        );

    $this->actingAs($user)
        ->withSession(['business_id' => $business->id, 'branch_id' => $branchB->id])
        ->get(route('dashboard', ['branch_scope' => 'all']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('branch_filter.scope', 'all')
            ->where('summary.today_sales', 350)
        );
});
