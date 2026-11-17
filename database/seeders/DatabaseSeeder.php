<?php

namespace Database\Seeders;

use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'password']);

        foreach (['Kai', 'Danny'] as $name) {
            Barber::factory()->create(['name' => $name]);
        }

        foreach ([
            ['Haircut', 'Classic cut with clipper and scissors.', 20, 30, 1],
            ['Haircut + Wash', 'Cut, shampoo and style.', 30, 45, 2],
            ['Cut + Beard', 'Full cut with beard trim and shaping.', 45, 60, 3],
        ] as [$name, $description, $price, $minutes, $points]) {
            Service::create(['name' => $name, 'description' => $description, 'price' => $price, 'duration_minutes' => $minutes, 'points' => $points]);
        }
    }
}
