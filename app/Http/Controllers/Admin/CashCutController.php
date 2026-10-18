<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloseDayRequest;
use App\Models\CashCut;
use App\Models\Setting;
use App\Services\CashCutService;
use Illuminate\Http\JsonResponse;

class CashCutController extends Controller
{
    /**
     * Cierra la caja del día de hoy con el efectivo contado.
     */
    public function closeDay(CloseDayRequest $request, CashCutService $cashCuts): JsonResponse
    {
        $cashCuts->catchUp();

        $cut = $cashCuts->closeDay(
            today(),
            (float) $request->validated('opening_cash'),
            (float) $request->validated('counted_cash'),
        );

        Setting::put('opening_cash', (string) $request->validated('opening_cash'));

        return response()->json(['cut' => $cut]);
    }

    /**
     * Detalle de un corte (por hora o del día).
     */
    public function show(CashCut $cashCut): JsonResponse
    {
        return response()->json(['cut' => $cashCut]);
    }
}
