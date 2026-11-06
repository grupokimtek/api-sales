<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'admin@test.com',
            'password' => Hash::make('password')
        ]);

        \App\Models\User::create([
            'name' => 'Usuario Demo',
            'email' => 'master@test.com',
            'password' => Hash::make('password')
        ]);

        $this->call(ProductSeeder::class);
    }
}
