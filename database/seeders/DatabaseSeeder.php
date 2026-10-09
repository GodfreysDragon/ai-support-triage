<?php

namespace Database\Seeders;

use App\Demo\SampleTickets;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Log in as test@example.com / "password" to see the same sample
        // tickets a "Try the demo" visitor gets.
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        SampleTickets::seedFor($user);
    }
}
