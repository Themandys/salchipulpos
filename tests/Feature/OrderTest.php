<?php

use App\Http\Middleware\EnsurePosAdmin;
use App\Models\Order;
use App\Models\Product;

it('shows the public menu with descriptions and calories', function () {
    Product::factory()->create(['name' => 'Spicy Pulpo', 'description' => 'Mezcla picante de la casa', 'calories' => 610]);

    $this->get(route('menu'))
        ->assertOk()
        ->assertSee('Spicy Pulpo')
        ->assertSee('Mezcla picante de la casa')
        ->assertSee('~610 kcal');
});

it('lets a customer place an order priced from the catalog', function () {
    $product = Product::factory()->create(['price' => 55]);

    $response = $this->postJson(route('api.orders.store'), [
        'customer_name' => 'Ana',
        'items' => [['id' => $product->id, 'qty' => 2]],
    ])->assertCreated()
        ->assertJsonPath('order.status', Order::StatusPending)
        ->assertJsonPath('order.total', 110);

    $this->getJson(route('api.orders.show', $response->json('order.uuid')))
        ->assertOk()
        ->assertJsonPath('order.customer_name', 'Ana');

    expect($product->fresh()->stock)->toBe(50);
});

it('does not let customers register sales or see pending orders', function () {
    $product = Product::factory()->create();

    $this->postJson(route('api.sales.store'), [
        'payment_method' => 'tarjeta',
        'items' => [['id' => $product->id, 'qty' => 1]],
    ])->assertUnauthorized();

    $this->getJson(route('api.admin.orders.index'))->assertUnauthorized();
});

it('marks the customer order as paid when the cashier charges it', function () {
    $product = Product::factory()->create(['price' => 70]);
    $order = Order::factory()->create([
        'items' => [['product_id' => $product->id, 'name' => $product->name, 'price' => 70, 'quantity' => 1, 'subtotal' => 70]],
        'total' => 70,
    ]);

    $this->withSession([EnsurePosAdmin::SessionKey => true]);

    $this->getJson(route('api.admin.orders.index'))->assertJsonCount(1, 'orders');

    $this->postJson(route('api.sales.store'), [
        'payment_method' => 'efectivo',
        'paid' => 100,
        'items' => [['id' => $product->id, 'qty' => 1]],
        'order_id' => $order->id,
    ])->assertCreated();

    expect($order->fresh())
        ->status->toBe(Order::StatusPaid)
        ->sale_id->not->toBeNull();

    $this->getJson(route('api.admin.orders.index'))->assertJsonCount(0, 'orders');
});

it('lets the cashier cancel a pending order', function () {
    $order = Order::factory()->create();

    $this->withSession([EnsurePosAdmin::SessionKey => true])
        ->postJson(route('api.admin.orders.cancel', $order))
        ->assertOk();

    expect($order->fresh()->status)->toBe(Order::StatusCancelled);
});
