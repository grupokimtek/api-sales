<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;

Route::get('/test', function () {
  return response()->json([
    'app' => config('app.name'),
    'env' => app()->environment(),
    'version' => app()->version(),
    'status' => 'ok',
  ]);
});

// Listar productos
Route::get('/products', [ProductController::class, 'listar']);

// Listar ventas
Route::get('/sales', [SaleController::class, 'listar']);
// Crear venta
Route::post('/sales', [SaleController::class, 'crearVenta']);
// Eliminar venta
Route::delete('/sales/{id}', [SaleController::class, 'eliminar']);
