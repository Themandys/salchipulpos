<?php

use App\Http\Middleware\EnsurePosAdmin;
use App\Models\Product;

it('preserves customized variants, prices and calories through checkout', function () {
    $product = Product::factory()->create([
        'price' => 95,
        'calories' => 780,
        'menu_options' => ['choices' => ['Dip' => ['Ranch', 'BBQ Ahumada']], 'combo' => true],
    ]);
    $drink = Product::factory()->create([
        'menu_code' => Product::DrinkCode,
        'is_addon' => true,
        'price' => 25,
        'calories' => 150,
        'menu_options' => ['choices' => ['Bebida' => ['Refresco', 'Té helado']]],
    ]);
    $cheddar = Product::factory()->create(['is_addon' => true, 'price' => 20, 'calories' => 150]);

    $order = $this->postJson(route('api.orders.store'), [
        'customer_name' => 'Ana',
        'items' => [
            ['id' => $product->id, 'qty' => 2, 'choices' => ['Dip' => 'BBQ Ahumada'], 'combo' => 'Té helado', 'extras' => [['id' => $cheddar->id]]],
            ['id' => $product->id, 'qty' => 1, 'choices' => ['Dip' => 'Ranch']],
        ],
    ])->assertCreated()
        ->assertJsonPath('order.total', 375)
        ->assertJsonPath('order.calories', 2940)
        ->assertJsonPath('order.items.0.details.labels.0', 'Dip: BBQ Ahumada')
        ->assertJsonPath('order.items.0.details.labels.1', 'Hazlo Combo: Té helado (355 ml)')
        ->json('order');

    $product->update(['price' => 120]);

    $this->withSession([EnsurePosAdmin::SessionKey => true])
        ->postJson(route('api.sales.store'), ['order_id' => $order['number'], 'payment_method' => 'tarjeta'])
        ->assertCreated()
        ->assertJsonPath('sale.total', 375)
        ->assertJsonPath('sale.items.0.details.calories', 1080)
        ->assertJsonPath('sale.items.0.details.labels.2', $cheddar->name);

    expect($product->fresh()->stock)->toBe(47)
        ->and($drink->fresh()->stock)->toBe(48)
        ->and($cheddar->fresh()->stock)->toBe(48);

    $this->postJson(route('api.sales.store'), ['order_id' => $order['number'], 'payment_method' => 'tarjeta'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('order_id');
});

it('checks shared beverage stock across an included drink and a combo upgrade', function () {
    Product::factory()->create(['menu_code' => Product::DrinkCode, 'is_addon' => true, 'stock' => 1, 'menu_options' => ['choices' => ['Bebida' => ['Refresco']]]]);
    $bundle = Product::factory()->create(['menu_options' => ['includes_drink' => true, 'choices' => ['Bebida (355 ml)' => ['Refresco']]]]);
    $product = Product::factory()->create(['menu_options' => ['combo' => true]]);

    $this->postJson(route('api.orders.store'), [
        'customer_name' => 'Ana',
        'items' => [
            ['id' => $bundle->id, 'qty' => 1],
            ['id' => $product->id, 'qty' => 1, 'combo' => 'Refresco'],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('items');
});

it('rejects a sauce that does not belong to the selected product', function () {
    $product = Product::factory()->create(['menu_options' => ['choices' => ['Salsa' => ['Buffalo']]]]);

    $this->postJson(route('api.orders.store'), [
        'customer_name' => 'Ana',
        'items' => [['id' => $product->id, 'qty' => 1, 'choices' => ['Salsa' => 'BBQ Ahumada']]],
    ])->assertUnprocessable()->assertJsonValidationErrors('items');
});
