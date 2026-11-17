<?php

namespace Database\Factories;

use App\Enums\QueueTicketStatus;
use App\Models\Customer;
use App\Models\QueueTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueTicket>
 */
class QueueTicketFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'queue_date' => today(),
            'number' => fake()->unique()->numberBetween(1, 100000),
            'status' => QueueTicketStatus::Waiting,
        ];
    }
}
