<?php

namespace App\Services;

use App\Models\CashCut;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CashCutService
{
    /**
     * Summarize every sale between two moments.
     *
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     sales_count: int,
     *     total: float,
     *     cash_total: float,
     *     card_total: float,
     *     summary: array{
     *         average_ticket: float,
     *         first_folio: int|null,
     *         last_folio: int|null,
     *         products: list<array{name: string, quantity: int, total: float}>,
     *         hours: list<array{hour: int, sales_count: int, total: float}>
     *     }
     * }
     */
    public function summarize(CarbonInterface $from, CarbonInterface $to): array
    {
        $sales = Sale::query()
            ->with('items')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('folio')
            ->get();

        return $this->summarizeSales($sales, $from, $to);
    }

    /**
     * Create the hourly cuts for every finished hour that had sales and is still missing one.
     * Also closes every past day that has sales but no complete daily cut.
     *
     * @return int Number of cuts created or refreshed.
     */
    public function catchUp(): int
    {
        return $this->catchUpHourly() + $this->catchUpDaily();
    }

    /**
     * Record (or refresh) the daily cut for the given date.
     */
    public function closeDay(CarbonInterface $date, ?float $openingCash = null, ?float $countedCash = null): CashCut
    {
        $start = Carbon::parse($date)->startOfDay();
        $end = $start->copy()->endOfDay();

        $existing = CashCut::query()
            ->where('type', CashCut::TypeDaily)
            ->where('period_start', $start)
            ->first();

        $data = $this->summarize($start, $end);

        $cut = $existing ?? new CashCut(['type' => CashCut::TypeDaily]);
        $cut->fill([
            ...$data,
            'period_start' => $start,
            'period_end' => $end,
            'opening_cash' => $openingCash ?? $existing?->opening_cash,
            'counted_cash' => $countedCash ?? $existing?->counted_cash,
            'closed_at' => now(),
        ])->save();

        return $cut;
    }

    private function catchUpHourly(): int
    {
        $lastEnd = CashCut::query()->where('type', CashCut::TypeHourly)->max('period_end');
        $currentHour = now()->startOfHour();

        $sales = Sale::query()
            ->with('items')
            ->when($lastEnd, fn ($query) => $query->where('created_at', '>', $lastEnd))
            ->where('created_at', '<', $currentHour)
            ->orderBy('folio')
            ->get();

        $created = 0;

        foreach ($sales->groupBy(fn (Sale $sale) => $sale->created_at->format('Y-m-d H')) as $hourKey => $hourSales) {
            $start = Carbon::createFromFormat('Y-m-d H', $hourKey)->startOfHour();
            $end = $start->copy()->endOfHour();

            CashCut::query()->firstOrCreate(
                ['type' => CashCut::TypeHourly, 'period_start' => $start],
                [...$this->summarizeSales($hourSales, $start, $end), 'period_end' => $end, 'closed_at' => now()],
            );

            $created++;
        }

        return $created;
    }

    private function catchUpDaily(): int
    {
        $today = now()->startOfDay();
        $lastStart = CashCut::query()->where('type', CashCut::TypeDaily)->max('period_start');

        $days = Sale::query()
            ->when($lastStart, fn ($query) => $query->where('created_at', '>=', $lastStart))
            ->where('created_at', '<', $today)
            ->pluck('created_at')
            ->map(fn (CarbonInterface $createdAt) => $createdAt->format('Y-m-d'))
            ->unique();

        $refreshed = 0;

        foreach ($days as $day) {
            $start = Carbon::parse($day)->startOfDay();

            $cut = CashCut::query()
                ->where('type', CashCut::TypeDaily)
                ->where('period_start', $start)
                ->first();

            if ($cut === null || $cut->closed_at->lessThan($cut->period_end)) {
                $this->closeDay($start);
                $refreshed++;
            }
        }

        return $refreshed;
    }

    /**
     * @param  Collection<int, Sale>  $sales
     * @return array<string, mixed>
     */
    private function summarizeSales(Collection $sales, CarbonInterface $from, CarbonInterface $to): array
    {
        $total = round($sales->sum('total'), 2);
        $cashTotal = round($sales->where('payment_method', Sale::MethodCash)->sum('total'), 2);

        $products = $sales->flatMap->items
            ->groupBy('product_name')
            ->map(fn (Collection $items, int|string $name) => [
                'name' => (string) $name,
                'quantity' => (int) $items->sum('quantity'),
                'total' => round($items->sum('subtotal'), 2),
            ])
            ->sortByDesc('quantity')
            ->values()
            ->all();

        $hours = $sales
            ->groupBy(fn (Sale $sale) => (int) $sale->created_at->format('G'))
            ->map(fn (Collection $hourSales, int $hour) => [
                'hour' => $hour,
                'sales_count' => $hourSales->count(),
                'total' => round($hourSales->sum('total'), 2),
            ])
            ->sortKeys()
            ->values()
            ->all();

        return [
            'period_start' => $from->toDateTimeString(),
            'period_end' => $to->toDateTimeString(),
            'sales_count' => $sales->count(),
            'total' => $total,
            'cash_total' => $cashTotal,
            'card_total' => round($total - $cashTotal, 2),
            'summary' => [
                'average_ticket' => $sales->isEmpty() ? 0.0 : round($total / $sales->count(), 2),
                'first_folio' => $sales->first()?->folio,
                'last_folio' => $sales->last()?->folio,
                'products' => $products,
                'hours' => $hours,
            ],
        ];
    }
}
