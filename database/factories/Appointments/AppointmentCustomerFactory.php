<?php

namespace Database\Factories\Appointments;

use App\Models\Appointments\AppointmentCustomer;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentCustomerFactory extends Factory
{
    protected $model = AppointmentCustomer::class;

    public function definition(): array
    {
        return [
            'business_id' => null,
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'notes' => null,
        ];
    }
}
