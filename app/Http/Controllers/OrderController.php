<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\OrderItems;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /**
     * Guarda el pedido de un cliente para que lo cobren en caja.
     */
    public function store(StoreOrderRequest $request, OrderItems $orderItems): JsonResponse
    {
        $validated = $request->validated();
        $items = $orderItems->price($validated['items']);

        $order = Order::query()->create([
            'customer_name' => trim($validated['customer_name']),
            'note' => $validated['note'] ?? null,
            'status' => Order::StatusPending,
            'items' => $items->all(),
            'total' => round($items->sum('subtotal'), 2),
        ]);

        return response()->json(['order' => $this->present($order)], 201);
    }

    /**
     * Estado del pedido para la pantalla del cliente.
     */
    public function show(Order $order): JsonResponse
    {
        return response()->json(['order' => $this->present($order)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Order $order): array
    {
        return [
            'number' => $order->id,
            'uuid' => $order->uuid,
            'status' => $order->status,
            'customer_name' => $order->customer_name,
            'items' => $order->items,
            'total' => $order->total,
            'calories' => collect($order->items)->every(fn (array $item) => isset($item['details']['calories']))
                ? collect($order->items)->sum(fn (array $item) => $item['details']['calories'] * $item['quantity'])
                : null,
        ];
    }
}
