<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{


    // Para listado de productos
    public function listar(){
        $productos = Product::select('id', 'name', 'price', 'stock')->get();
        return response()->json($productos, 200);
    }

}
