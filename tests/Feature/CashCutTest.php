<?php

use App\Http\Middleware\EnsurePosAdmin;
use App\Models\CashCut;
use App\Models\Sale;
use App\Services\CashCutService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-17 15:20:00'));
});

function saleAt(string $time, float $total, string $method = Sale::MethodCash): Sale
{
    $sale = Sale::factory()->create(['total' => $total, 'paid' => $total, 'payment_method' => $method]);
    $sale->forceFill(['created_at' => Carbon::parse($time)])->save();
    $sale->items()->create(['product_name' => 'Salchipulpo', 'price' => $total, 'quantity' => 1, 'subtotal' => $total]);

    return $sale;
}

it('creates one cut per finished hour with sales and skips the current hour', function () {
    saleAt('2026-10-17 13:05:00', 35);
    saleAt('2026-10-17 13:40:00', 50, Sale::MethodCard);
    saleAt('2026-10-17 14:10:00', 65);
    saleAt('2026-10-17 15:01:00', 20);

    $this->artisan('pos:cortes')->assertSuccessful();

    $cuts = CashCut::query()->where('type', CashCut::TypeHourly)->orderBy('period_start')->get();

    expect($cuts)->toHaveCount(2)
        ->and($cuts[0]->period_start->format('H:i'))->toBe('13:00')
        ->and($cuts[0]->sales_count)->toBe(2)
        ->and($cuts[0]->total)->toBe(85.0)
        ->and($cuts[0]->cash_total)->toBe(35.0)
        ->and($cuts[0]->card_total)->toBe(50.0)
        ->and($cuts[1]->total)->toBe(65.0);
});

it('does not duplicate hourly cuts when it runs again', function () {
    saleAt('2026-10-17 13:05:00', 35);

    app(CashCutService::class)->catchUp();
    app(CashCutService::class)->catchUp();

    expect(CashCut::query()->where('type', CashCut::TypeHourly)->count())->toBe(1);
});

it('closes previous days automatically', function () {
    saleAt('2026-10-16 20:00:00', 100);
    saleAt('2026-10-16 21:30:00', 40, Sale::MethodCard);

    app(CashCutService::class)->catchUp();

    $daily = CashCut::query()->where('type', CashCut::TypeDaily)->sole();

    expect($daily->period_start->toDateString())->toBe('2026-10-16')
        ->and($daily->total)->toBe(140.0)
        ->and($daily->summary['hours'])->toHaveCount(2);
});

it('closes the day with the counted cash and reports the difference', function () {
    saleAt('2026-10-17 10:00:00', 200);
    saleAt('2026-10-17 11:00:00', 100, Sale::MethodCard);

    $this->withSession([EnsurePosAdmin::SessionKey => true])
        ->postJson(route('api.admin.cuts.close-day'), ['opening_cash' => 500, 'counted_cash' => 690])
        ->assertOk()
        ->assertJsonPath('cut.total', 300)
        ->assertJsonPath('cut.expected_cash', 700)
        ->assertJsonPath('cut.difference', -10);
});
