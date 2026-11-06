<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Product::insert([
            [
                'name'  => 'Laptop Lenovo ThinkPad',
                'price' => 7500.00,
                'stock' => 10000,
                'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8'
            ],
            [
                'name'  => 'Mouse Inalámbrico Logitech',
                'price' => 150.53,
                'stock' => 50000,
                'image' => 'https://images.unsplash.com/photo-1587829741301-dc798b83add3'
            ],
            [
                'name'  => 'Teclado Mecánico RGB',
                'price' => 450.00,
                'stock' => 20000,
                'image' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8'
            ],
            [
                'name'  => 'Monitor 24" Full HD',
                'price' => 1200.00,
                'stock' => 15000,
                'image' => 'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04'
            ],
            [
                'name'  => 'Auriculares Gamer',
                'price' => 320.99,
                'stock' => 18000,
                'image' => 'https://images.unsplash.com/photo-1585386959984-a41552231693'
            ],
            [
                'name'  => 'Webcam HD 1080p',
                'price' => 280.00,
                'stock' => 12000,
                'image' => 'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04'
            ],
            [
                'name'  => 'Disco SSD 1TB',
                'price' => 980.00,
                'stock' => 25000,
                'image' => 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9'
            ],
            [
                'name'  => 'Memoria RAM 16GB DDR4',
                'price' => 560.00,
                'stock' => 30000,
                'image' => 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9'
            ],
            [
                'name'  => 'Impresora Multifunción',
                'price' => 1850.00,
                'stock' => 8000,
                'image' => 'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04'
            ],
            [
                'name'  => 'Tablet Samsung Galaxy',
                'price' => 2100.00,
                'stock' => 9000,
                'image' => 'https://images.unsplash.com/photo-1587825140708-dfaf72ae4b04'
            ],
        ]);
    }
}
