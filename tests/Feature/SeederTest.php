<?php

use App\Models\Product;
use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;

it('seeds only the official menu on a fresh database', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Product::query()->count())->toBe(22)
        ->and(Product::query()->active()->where('is_addon', false)->count())->toBe(16)
        ->and(Product::query()->active()->where('is_addon', true)->count())->toBe(6);
});

it('does not overwrite settings changed by the owner when seeding again', function () {
    $this->seed(DatabaseSeeder::class);
    Setting::put('business_pin', '9876');

    $this->seed(DatabaseSeeder::class);

    expect(Setting::value('business_pin'))->toBe('9876');
});
