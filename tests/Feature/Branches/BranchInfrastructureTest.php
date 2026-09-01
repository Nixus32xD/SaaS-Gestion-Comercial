<?php

use App\Models\Branch;
use App\Models\Business;
use App\Models\User;
use App\Support\CurrentBranch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('a new business receives one active default branch', function () {
    $business = Business::factory()->create();

    $branch = $business->defaultBranch;

    expect($business->branches)->toHaveCount(1)
        ->and($branch)->not->toBeNull()
        ->and($branch->name)->toBe('Sucursal Principal')
        ->and($branch->code)->toBe(Branch::DEFAULT_CODE)
        ->and($branch->is_active)->toBeTrue()
        ->and($branch->is_default)->toBeTrue();
});

test('a business cannot have more than one default branch', function () {
    $business = Business::factory()->create();

    expect(fn () => Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Centro',
        'code' => 'centro',
        'is_active' => true,
        'is_default' => true,
    ]))->toThrow(QueryException::class);
});

test('business middleware resolves and persists the default branch', function () {
    $business = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('business.has_multiple_branches', false)
    );

    expect(session('branch_id'))->toBe($business->defaultBranch->id);
});

test('business user can select an active branch from their business', function () {
    $business = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();
    $branch = Branch::query()->create([
        'business_id' => $business->id,
        'name' => 'Sucursal Centro',
        'code' => 'centro',
        'is_active' => true,
        'is_default' => false,
    ]);

    $response = $this->actingAs($user)
        ->from('/dashboard')
        ->put('/branches/current', ['branch_id' => $branch->id]);

    $response->assertRedirect('/dashboard');

    expect(session('branch_id'))->toBe($branch->id);
});

test('business user cannot select a branch from another business', function () {
    $business = Business::factory()->create();
    $foreignBusiness = Business::factory()->create();
    $user = User::factory()->businessAdmin($business->id)->create();

    $this->actingAs($user)
        ->put('/branches/current', ['branch_id' => $foreignBusiness->defaultBranch->id])
        ->assertForbidden();
});

test('current branch falls back to the default branch when session references another business', function () {
    $business = Business::factory()->create();
    $foreignBusiness = Business::factory()->create();

    $branch = (new CurrentBranch)->resolve($business, $foreignBusiness->defaultBranch->id);

    expect($branch->id)->toBe($business->defaultBranch->id)
        ->and($branch->business_id)->toBe($business->id);
});

test('branch migration audit reports businesses without a default branch without writing data', function () {
    DB::table('businesses')->insert([
        'name' => 'Comercio histórico sin sucursal',
        'slug' => 'comercio-historico-sin-sucursal',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('branches:audit-migration')
        ->expectsOutputToContain('Comercios sin sucursal principal')
        ->assertExitCode(0);

    expect(Branch::query()->where('business_id', DB::table('businesses')->where('slug', 'comercio-historico-sin-sucursal')->value('id'))->exists())
        ->toBeFalse();
});
