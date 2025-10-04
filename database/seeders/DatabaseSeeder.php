<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Utwórz administratora
        User::updateOrCreate(
            ['email' => 'admin@admin.pl'],
            [
                'name' => 'Administrator',
                'email' => 'admin@admin.pl',
                'password' => bcrypt('admin'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // Utwórz testowego użytkownika
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => bcrypt('password'),
                'is_admin' => false,
                'email_verified_at' => now(),
            ]
        );
    }
}
