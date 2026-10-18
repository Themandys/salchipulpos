<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    /**
     * Registra un cobro, descuenta inventario y devuelve el ticket.
     */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $sale = DB::transaction(function () use ($validated): Sale {
            $total = 0.0;
            $lines = [];

            foreach ($validated['items'] as $item) {
                $product = Product::query()->lockForUpdate()->findOrFail($item['id']);

                if ($product->stock < $item['qty']) {
                    throw ValidationException::withMessages([
                        'items' => "Stock insuficiente para \"{$product->name}\". Quedan {$product->stock}.",
                    ]);
                }

                $subtotal = round($product->price * $item['qty'], 2);
                $total += $subtotal;
                $product->decrement('stock', $item['qty']);

                $lines[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'price' => $product->price,
                    'quantity' => $item['qty'],
                    'subtotal' => $subtotal,
                ];
            }

            $total = round($total, 2);
            $isCash = $validated['payment_method'] === Sale::MethodCash;
            $paid = $isCash ? round((float) $validated['paid'], 2) : $total;

            if ($paid < $total) {
                throw ValidationException::withMessages([
                    'paid' => 'El pago es menor que el total.',
                ]);
            }

            $sale = Sale::query()->create([
                'folio' => (Sale::query()->max('folio') ?? 0) + 1,
                'total' => $total,
                'paid' => $paid,
                'change' => round($paid - $total, 2),
                'payment_method' => $validated['payment_method'],
            ]);

            $sale->items()->createMany($lines);

            if (! empty($validated['order_id'])) {
                Order::query()
                    ->whereKey($validated['order_id'])
                    ->where('status', Order::StatusPending)
                    ->update(['status' => Order::StatusPaid, 'sale_id' => $sale->id]);
            }

            return $sale;
        });

        return response()->json(['sale' => $sale->load('items')], 201);
    }
}
