<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CashCutService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;

class PosController extends Controller
{
    /**
     * Muestra la vista principal del punto de venta.
     */
    public function index(): View
    {
        return view('pos', ['settings' => Setting::publicSettings()]);
    }

    /**
     * Catálogo activo para la pantalla de venta. También genera los cortes por hora pendientes.
     */
    public function catalog(CashCutService $cashCuts): JsonResponse
    {
        $cashCuts->catchUp();

        return response()->json([
            'settings' => Setting::publicSettings(),
            'categories' => Category::query()->orderBy('id')->get(['id', 'name']),
            'products' => Product::query()->active()->orderBy('category_id')->orderBy('id')->get(),
        ]);
    }
}
