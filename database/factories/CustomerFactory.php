<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '60'.fake()->unique()->numerify('1########'),
            'name' => fake()->firstName(),
            'points' => 0,
            'notifications_enabled' => true,
        ];
    }

    public function withFreeCut(): static
    {
        return $this->state(['points' => Customer::POINTS_FOR_FREE_CUT]);
    }
}
