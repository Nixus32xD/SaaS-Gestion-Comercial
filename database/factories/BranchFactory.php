<?php

namespace Database\Factories;

use App\Domain\Branches\Models\Branch;
use App\Domain\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => fake()->city(),
            'code' => fake()->unique()->bothify('BR###'),
            'status' => 'active',
            'is_main' => false,
            'address' => fake()->address(),
        ];
    }
}
