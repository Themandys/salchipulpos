<?php

use App\Http\Middleware\EnsurePosAdmin;
use App\Models\Product;
use App\Models\Sale;

beforeEach(function () {
    $this->withSession([EnsurePosAdmin::SessionKey => true]);
});

it('registers a cash sale, deducts stock and returns the change', function () {
    $product = Product::factory()->create(['price' => 35, 'stock' => 10]);

    $this->postJson(route('api.sales.store'), [
        'payment_method' => 'efectivo',
        'paid' => 100,
        'items' => [['id' => $product->id, 'qty' => 2]],
    ])
        ->assertCreated()
        ->assertJsonPath('sale.folio', 1)
        ->assertJsonPath('sale.total', 70)
        ->assertJsonPath('sale.change', 30)
        ->assertJsonPath('sale.items.0.quantity', 2);

    expect($product->fresh()->stock)->toBe(8);
});

it('charges card sales for the exact total', function () {
    $product = Product::factory()->create(['price' => 50]);

    $this->postJson(route('api.sales.store'), [
        'payment_method' => 'tarjeta',
        'items' => [['id' => $product->id, 'qty' => 1]],
    ])
        ->assertCreated()
        ->assertJsonPath('sale.paid', 50)
        ->assertJsonPath('sale.change', 0);
});

it('rolls back the whole sale when one product runs out of stock', function () {
    $available = Product::factory()->create(['stock' => 10]);
    $scarce = Product::factory()->create(['stock' => 1]);

    $this->postJson(route('api.sales.store'), [
        'payment_method' => 'efectivo',
        'paid' => 1000,
        'items' => [['id' => $available->id, 'qty' => 3], ['id' => $scarce->id, 'qty' => 2]],
    ])->assertUnprocessable()->assertJsonValidationErrors('items');

    expect($available->fresh()->stock)->toBe(10)
        ->and(Sale::query()->count())->toBe(0);
});

it('rejects cash payments lower than the total', function () {
    $product = Product::factory()->create(['price' => 65]);

    $this->postJson(route('api.sales.store'), [
        'payment_method' => 'efectivo',
        'paid' => 50,
        'items' => [['id' => $product->id, 'qty' => 1]],
    ])->assertUnprocessable()->assertJsonValidationErrors('paid');

    expect($product->fresh()->stock)->toBe(50);
});
