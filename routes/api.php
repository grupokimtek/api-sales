<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/* Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum'); */


// Productos
Route::get('/products', [ProductController::class, 'index']);

// Ventas
Route::get('/sales', [SaleController::class, 'index']);

Route::post('/sales', [SaleController::class, 'store']);

Route::delete('/sales/{id}', [SaleController::class, 'destroy'])
    ->whereNumber('id');