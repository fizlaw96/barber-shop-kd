<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /**
     * Starting prices are the middle of the estimated range and durations the upper end,
     * so bookings never run short. The owner adjusts both on the Manage page.
     * Points: under RM20 = 1, under RM40 = 2, otherwise 3.
     */
    public function run(): void
    {
        $services = [
            ['Basic Haircut', 'Potong rambut asas.', 18, 30],
            ['Skin Fade / Taper Fade', 'Fade kemas dari kulit atau taper.', 25, 45],
            ['Kids Haircut', 'Potong rambut kanak-kanak.', 12, 30],
            ['Haircut + Hair Wash', 'Potong rambut dan cuci rambut.', 30, 45],
            ['Beard Trim', 'Trim dan kemaskan janggut.', 12, 15],
            ['Shave', 'Cukur janggut dan misai.', 15, 20],
            ['Hair Wash', 'Cuci rambut.', 12, 15],
            ['Hair Styling', 'Gaya rambut.', 15, 20],
            ['Hair Colouring', 'Warna rambut.', 75, 90],
            ['Hair Treatment', 'Rawatan rambut.', 45, 45],
            ['Head Massage', 'Urutan kepala.', 20, 20],
            ['Haircut + Beard Trim', 'Pakej combo potong rambut dan trim janggut.', 30, 40],
            ['Haircut + Hair Wash + Styling', 'Pakej combo potong, cuci dan gaya rambut.', 35, 45],
            ['Haircut + Shave + Hair Wash', 'Pakej combo potong, cukur dan cuci rambut.', 40, 60],
            ['Premium Grooming Package', 'Pakej grooming penuh.', 60, 75],
        ];

        foreach ($services as [$name, $description, $price, $minutes]) {
            Service::updateOrCreate(['name' => $name], [
                'description' => $description,
                'price' => $price,
                'duration_minutes' => $minutes,
                'points' => $price < 20 ? 1 : ($price < 40 ? 2 : 3),
                'is_active' => true,
            ]);
        }
    }
}
