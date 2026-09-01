<?php

use App\Models\Business;
use App\Models\Branch;
use App\Models\Product;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('superadmin business index is paginated at ten and supports server side search', function () {
    $superAdmin = User::factory()->superadmin()->create();

    foreach (range(1, 12) as $index) {
        Business::factory()->create([
            'name' => "Comercio {$index}",
            'slug' => "comercio-{$index}",
        ]);
    }

    $target = Business::factory()->create([
        'name' => 'Comercio Prueba Cinthia',
        'slug' => 'comercio-prueba-cinthia',
        'email' => 'cinthia@comercio.test',
    ]);

    User::factory()->businessAdmin($target->id)->create([
        'name' => 'Cinthia',
        'email' => 'cinthia-admin@comercio.test',
    ]);

    $this->actingAs($superAdmin)
        ->get(route('admin.businesses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Businesses/Index')
            ->has('businesses.data', 10)
            ->where('businesses.per_page', 10)
            ->where('businesses.total', 13)
        );

    $this->actingAs($superAdmin)
        ->get(route('admin.businesses.index', ['search' => 'Cinthia']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Businesses/Index')
            ->has('businesses.data', 1)
            ->where('businesses.data.0.id', $target->id)
            ->where('businesses.data.0.name', 'Comercio Prueba Cinthia')
            ->where('businesses.per_page', 10)
            ->where('businesses.total', 1)
            ->where('filters.search', 'Cinthia')
        );
});

test('superadmin can archive a business without deleting its products', function () {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create([
        'name' => 'Comercio Prueba Uriel',
        'slug' => 'comercio-prueba-uriel',
        'is_active' => true,
    ]);

    User::factory()->businessAdmin($business->id)->create();

    $product = Product::query()->create([
        'business_id' => $business->id,
        'name' => 'Producto de prueba',
        'slug' => 'producto-de-prueba',
        'unit_type' => 'unit',
        'sale_price' => 100,
        'cost_price' => 50,
        'stock' => 10,
        'min_stock' => 1,
        'is_active' => true,
    ]);

    $this->actingAs($superAdmin)
        ->delete(route('admin.businesses.archive', $business))
        ->assertRedirect(route('admin.businesses.index'));

    $archivedBusiness = Business::withTrashed()->find($business->id);

    expect($archivedBusiness)->not()->toBeNull();
    expect($archivedBusiness->trashed())->toBeTrue();
    expect($archivedBusiness->is_active)->toBeFalse();
    expect(Product::query()->whereKey($product->id)->exists())->toBeTrue();

    $this->actingAs($superAdmin)
        ->get(route('admin.businesses.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Businesses/Index')
            ->where('businesses.total', 0)
        );
});

test('superadmin can manage a business branches from its edit panel', function () {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();

    $this->actingAs($superAdmin)
        ->get(route('admin.businesses.edit', $business))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Businesses/Edit')
            ->has('branches', 1)
            ->where('branches.0.id', $business->defaultBranch->id)
            ->where('branches.0.is_default', true)
        );

    $this->actingAs($superAdmin)
        ->post(route('admin.businesses.branches.store', $business), [
            'name' => 'Sucursal Centro',
            'code' => 'Centro Mendoza',
            'address' => 'San Martín 120',
            'phone' => '261-555-1000',
            'email' => 'centro@example.test',
            'is_active' => true,
        ])
        ->assertRedirect(route('admin.businesses.edit', $business));

    $branch = Branch::query()
        ->forBusiness($business->id)
        ->where('code', 'centro-mendoza')
        ->firstOrFail();

    expect($branch->name)->toBe('Sucursal Centro')
        ->and($branch->is_active)->toBeTrue()
        ->and($branch->is_default)->toBeFalse();

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.branches.update', [$business, $branch]), [
            'name' => 'Sucursal Centro Norte',
            'code' => 'centro-norte',
            'address' => 'San Martín 120',
            'phone' => '261-555-2000',
            'email' => 'norte@example.test',
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.businesses.edit', $business));

    expect($branch->fresh())
        ->name->toBe('Sucursal Centro Norte')
        ->code->toBe('centro-norte')
        ->is_active->toBeFalse();
});

test('superadmin cannot deactivate a business default branch or update another business branch', function () {
    $superAdmin = User::factory()->superadmin()->create();
    $business = Business::factory()->create();
    $otherBusiness = Business::factory()->create();
    $defaultBranch = $business->defaultBranch;

    $this->actingAs($superAdmin)
        ->from(route('admin.businesses.edit', $business))
        ->put(route('admin.businesses.branches.update', [$business, $defaultBranch]), [
            'name' => $defaultBranch->name,
            'code' => $defaultBranch->code,
            'is_active' => false,
        ])
        ->assertRedirect(route('admin.businesses.edit', $business))
        ->assertSessionHasErrors('is_active');

    $this->actingAs($superAdmin)
        ->put(route('admin.businesses.branches.update', [$business, $otherBusiness->defaultBranch]), [
            'name' => 'No permitido',
            'code' => 'no-permitido',
            'is_active' => true,
        ])
        ->assertNotFound();
});
