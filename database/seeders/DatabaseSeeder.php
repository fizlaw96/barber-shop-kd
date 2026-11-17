<?php

namespace Database\Seeders;

use App\Models\Barber;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(['email' => 'owner@example.com'], ['name' => 'Owner', 'password' => 'password']);

        foreach (['Kai', 'Danny'] as $name) {
            Barber::firstOrCreate(['name' => $name]);
        }

        $this->call(ServiceSeeder::class);
    }
}
