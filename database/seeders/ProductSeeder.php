<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('products')->insert([
            [
                'name' => 'Laptop Lenovo',
                'price' => 7500.00,
                'stock' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Mouse Logitech',
                'price' => 150.00,
                'stock' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Teclado Mecanico',
                'price' => 500.00,
                'stock' => 15,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Monitor Samsung',
                'price' => 1800.00,
                'stock' => 8,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
