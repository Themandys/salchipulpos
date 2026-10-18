<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /**
     * Guarda el pedido de un cliente para que lo cobren en caja.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $products = Product::query()->whereKey(collect($validated['items'])->pluck('id'))->get()->keyBy('id');

        $items = collect($validated['items'])->map(fn (array $item) => [
            'product_id' => $item['id'],
            'name' => $products[$item['id']]->name,
            'price' => $products[$item['id']]->price,
            'quantity' => $item['qty'],
            'subtotal' => round($products[$item['id']]->price * $item['qty'], 2),
        ]);

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
        ];
    }
}
