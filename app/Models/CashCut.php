<?php

namespace App\Models;

use Database\Factories\CashCutFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'type', 'period_start', 'period_end', 'sales_count', 'total', 'cash_total', 'card_total',
    'opening_cash', 'counted_cash', 'summary', 'closed_at',
])]
class CashCut extends Model
{
    /** @use HasFactory<CashCutFactory> */
    use HasFactory;

    public const string TypeHourly = 'hourly';

    public const string TypeDaily = 'daily';

    /**
     * @var list<string>
     */
    protected $appends = ['expected_cash', 'difference'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'closed_at' => 'datetime',
            'sales_count' => 'integer',
            'total' => 'float',
            'cash_total' => 'float',
            'card_total' => 'float',
            'opening_cash' => 'float',
            'counted_cash' => 'float',
            'summary' => 'array',
        ];
    }

    /**
     * Cash that should be in the drawer: opening float plus cash sales.
     */
    public function getExpectedCashAttribute(): float
    {
        return round(($this->opening_cash ?? 0) + $this->cash_total, 2);
    }

    /**
     * Counted cash minus expected cash (negative means missing money).
     */
    public function getDifferenceAttribute(): ?float
    {
        return $this->counted_cash === null ? null : round($this->counted_cash - $this->expected_cash, 2);
    }
}
