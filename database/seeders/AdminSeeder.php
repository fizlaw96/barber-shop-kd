<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    /**
     * Create the admin from ADMIN_NAME / ADMIN_EMAIL / ADMIN_PASSWORD in .env.
     * Safe to re-run: it updates the same email, so it also resets a forgotten password.
     * Outside local and testing, ADMIN_PASSWORD is required so no default password is ever used live.
     */
    public function run(): void
    {
        $admin = config('queue_shop.admin');
        $password = $admin['password'];

        if (blank($password)) {
            if (! app()->environment('local', 'testing')) {
                $this->command?->error('ADMIN_PASSWORD is not set in .env. Set it, run php artisan config:clear, then seed again.');

                return;
            }

            $password = 'password';
        }

        User::updateOrCreate(
            ['email' => $admin['email']],
            ['name' => $admin['name'], 'password' => $password],
        );

        $this->command?->info("Admin ready: {$admin['email']}");
    }
}
