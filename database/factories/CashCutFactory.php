<?php

namespace Database\Factories;

use App\Models\CashCut;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashCut>
 */
class CashCutFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subHour()->startOfHour();

        return [
            'type' => CashCut::TypeHourly,
            'period_start' => $start,
            'period_end' => $start->copy()->endOfHour(),
            'sales_count' => 0,
            'total' => 0,
            'cash_total' => 0,
            'card_total' => 0,
            'summary' => [],
            'closed_at' => now(),
        ];
    }
}
