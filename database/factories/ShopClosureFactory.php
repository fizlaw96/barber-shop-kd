<?php

namespace Database\Factories;

use App\Models\ShopClosure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopClosure>
 */
class ShopClosureFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return ['closed_on' => fake()->unique()->dateTimeBetween('+1 day', '+1 year'), 'reason' => 'Public holiday'];
    }
}
