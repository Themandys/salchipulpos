<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashCut;
use App\Models\Category;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\CashCutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    /**
     * Todo lo que necesita el panel: resumen del día, hora en curso, cortes, inventario y ventas.
     */
    public function index(CashCutService $cashCuts): JsonResponse
    {
        $cashCuts->catchUp();

        $todayStart = now()->startOfDay();

        return response()->json([
            'today' => $cashCuts->summarize($todayStart, now()->endOfDay()),
            'current_hour' => $cashCuts->summarize(now()->startOfHour(), now()->endOfHour()),
            'today_close' => CashCut::query()
                ->where('type', CashCut::TypeDaily)
                ->where('period_start', $todayStart)
                ->first(),
            'hourly_cuts' => CashCut::query()
                ->where('type', CashCut::TypeHourly)
                ->where('period_start', '>=', $todayStart->copy()->subDays(1))
                ->latest('period_start')
                ->get(),
            'daily_cuts' => CashCut::query()
                ->where('type', CashCut::TypeDaily)
                ->latest('period_start')
                ->limit(60)
                ->get(),
            'sales' => Sale::query()
                ->with('items')
                ->where('created_at', '>=', $todayStart)
                ->latest('folio')
                ->limit(300)
                ->get(),
            'products' => Product::query()->with('category:id,name')->orderBy('category_id')->orderBy('id')->get(),
            'categories' => Category::query()->orderBy('id')->pluck('name'),
            'settings' => [...Setting::publicSettings(), 'opening_cash' => (float) Setting::value('opening_cash')],
        ]);
    }

    /**
     * Descarga en CSV las ventas de un rango de fechas (por defecto, hoy).
     */
    public function export(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = Carbon::parse($validated['from'] ?? today())->startOfDay();
        $to = Carbon::parse($validated['to'] ?? $from)->endOfDay();

        return response()->streamDownload(function () use ($from, $to): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Folio', 'Fecha', 'Hora', 'Método', 'Productos', 'Total', 'Pagó', 'Cambio']);

            Sale::query()
                ->with('items')
                ->whereBetween('created_at', [$from, $to])
                ->orderBy('folio')
                ->chunk(500, function ($sales) use ($output): void {
                    foreach ($sales as $sale) {
                        fputcsv($output, [
                            $sale->folio,
                            $sale->created_at->format('Y-m-d'),
                            $sale->created_at->format('H:i'),
                            $sale->payment_method,
                            $sale->items->map(fn ($item) => "{$item->quantity} {$item->product_name}")->implode('; '),
                            $sale->total,
                            $sale->paid,
                            $sale->change,
                        ]);
                    }
                });

            fclose($output);
        }, "ventas_{$from->format('Y-m-d')}_{$to->format('Y-m-d')}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
