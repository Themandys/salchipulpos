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
        abort_unless($order->status === Order::StatusPending, 422, 'Este pedido ya no está pendiente.');

        $order->update(['status' => Order::StatusCancelled]);

        return response()->json(['order' => $order]);
    }
}
