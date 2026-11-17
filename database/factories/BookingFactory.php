<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Barber;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDay()->setTime(11, 0);

        return [
            'customer_id' => Customer::factory(),
            'barber_id' => Barber::factory(),
            'service_id' => Service::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes(30),
            'status' => BookingStatus::Booked,
            'source' => 'web',
        ];
    }
}
