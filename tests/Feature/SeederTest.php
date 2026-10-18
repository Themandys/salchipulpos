<?php

use App\Models\Product;
use App\Models\Setting;
use Database\Seeders\DatabaseSeeder;

it('seeds only the official menu on a fresh database', function () {
    $this->seed(DatabaseSeeder::class);

    expect(Product::query()->count())->toBe(9)
        ->and(Product::query()->active()->count())->toBe(9);
});

it('does not overwrite settings changed by the owner when seeding again', function () {
    $this->seed(DatabaseSeeder::class);
    Setting::put('business_pin', '9876');

    $this->seed(DatabaseSeeder::class);

    expect(Setting::value('business_pin'))->toBe('9876');
});
