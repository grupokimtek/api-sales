<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function __construct(private SaleService $sales)
    {
    }

    public function index(): JsonResponse
    {
        $sales = Sale::with('details.product:id,name')->orderByDesc('id')->get();

        return response()->json(SaleResource::collection($sales), 200);
    }

    public function store(StoreSaleRequest $request): JsonResponse
    {
        $sale = $this->sales->create($request->validated('items'));

        return response()->json([
            'message' => 'Venta creada correctamente',
            'sale_id' => $sale->id,
            'total' => $sale->total,
        ], 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $sale = Sale::find($id);

        if (!$sale) {
            return response()->json(['message' => 'Venta no encontrada'], 404);
        }

        $this->sales->delete($sale);

        return response()->json(['message' => 'Venta eliminada correctamente'], 200);
    }
}
