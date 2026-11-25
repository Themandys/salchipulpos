<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    /**
     * Pedidos de clientes que esperan ser cobrados.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'orders' => Order::query()->pending()->oldest()->limit(100)->get(),
        ]);
    }

    /**
     * Descarta un pedido que el cliente no pagó.
     */
    public function cancel(Order $order): JsonResponse
    {
        $cancelled = Order::query()->pending()->whereKey($order->id)->update(['status' => Order::StatusCancelled]);
        abort_unless($cancelled, 422, 'Este pedido ya no está pendiente.');

        $order->refresh();

        return response()->json(['order' => $order]);
    }
}
