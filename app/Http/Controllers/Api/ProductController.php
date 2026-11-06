<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::query()
            ->get(['id', 'name', 'price', 'stock'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'price' => (float) $product->price,
                'stock' => $product->stock,
            ]);

        return response()->json($products);
    }
}
