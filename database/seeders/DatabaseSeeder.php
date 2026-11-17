<?php

namespace Database\Seeders;

use App\Models\Barber;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AdminSeeder::class);

        foreach (['Kai', 'Danny'] as $name) {
            Barber::firstOrCreate(['name' => $name]);
        }

        $this->call(ServiceSeeder::class);
    }
}
