<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'value'])]
class Setting extends Model
{
    public const array Defaults = [
        'business_name' => 'CUIDADO CON EL SALCHIPULPO',
        'ticket_footer' => '¡Gracias por tu compra!',
        'business_pin' => '1234',
        'opening_cash' => '0',
    ];

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * Read a setting, falling back to its default value.
     */
    public static function value(string $key): string
    {
        return static::query()->find($key)?->value ?? self::Defaults[$key] ?? '';
    }

    /**
     * Store a setting value.
     */
    public static function put(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    /**
     * Settings that are safe to show on the sales screen.
     *
     * @return array{business_name: string, ticket_footer: string}
     */
    public static function publicSettings(): array
    {
        return [
            'business_name' => static::value('business_name'),
            'ticket_footer' => static::value('ticket_footer'),
        ];
    }
}
