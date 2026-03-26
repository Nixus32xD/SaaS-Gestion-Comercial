<?php

namespace Database\Factories\Appointments;

use App\Models\Appointments\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'business_id' => null,
            'service_category_id' => null,
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'duration_minutes' => 30,
            'price' => fake()->randomFloat(2, 10, 200),
            'is_active' => true,
        ];
    }
}
