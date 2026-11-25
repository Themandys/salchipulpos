<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSaleRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\OrderItems;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleController extends Controller
{
    public function store(StoreSaleRequest $request, OrderItems $orderItems): JsonResponse
    {
        $validated = $request->validated();

        $sale = DB::transaction(function () use ($validated, $orderItems): Sale {
            $order = $this->pendingOrder($validated['order_id'] ?? null);

            $products = $order ? collect() : $this->lockProducts(array_column($validated['items'], 'id'));
            $lines = $order ? collect($order->items) : $this->counterItems($validated['items'], $products);
            $requirements = $orderItems->stockRequirements($lines);

            if ($order) {
                $products = $this->lockProducts(array_keys($requirements));
            }

            $orderItems->checkStock($requirements, $products);
            $total = round($lines->sum('subtotal'), 2);
            $paid = $validated['payment_method'] === Sale::MethodCash ? round((float) $validated['paid'], 2) : $total;

            if ($paid < $total) {
                throw ValidationException::withMessages(['paid' => 'El pago es menor que el total.']);
            }

            foreach ($requirements as $id => $quantity) {
                $products[$id]->decrement('stock', $quantity);
            }

            // Serialize folio assignment even when terminals sell different products.
            Setting::query()->lockForUpdate()->findOrFail('business_name');
            $sale = Sale::query()->create([
                'folio' => (Sale::query()->max('folio') ?? 0) + 1,
                'total' => $total,
                'paid' => $paid,
                'change' => round($paid - $total, 2),
                'payment_method' => $validated['payment_method'],
            ]);

            $sale->items()->createMany($lines->map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'product_name' => $line['name'],
                'price' => $line['price'],
                'quantity' => $line['quantity'],
                'subtotal' => $line['subtotal'],
                'details' => $line['details'] ?? null,
            ])->all());

            $order?->update(['status' => Order::StatusPaid, 'sale_id' => $sale->id]);

            return $sale;
        }, attempts: 3);

        return response()->json(['sale' => $sale->load('items')], 201);
    }

    private function pendingOrder(?int $id): ?Order
    {
        if ($id === null) {
            return null;
        }

        $order = Order::query()->pending()->lockForUpdate()->find($id);

        if (! $order) {
            throw ValidationException::withMessages(['order_id' => 'Ese pedido ya fue cobrado o cancelado.']);
        }

        return $order;
    }

    private function lockProducts(array $ids): Collection
    {
        return Product::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    private function counterItems(array $items, Collection $products): Collection
    {
        return collect($items)->map(function (array $item) use ($products): array {
            $product = $products->get($item['id']);

            if (! $product?->is_active) {
                throw ValidationException::withMessages(['items' => 'Uno de los productos ya no está disponible.']);
            }

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'quantity' => (int) $item['qty'],
                'subtotal' => round($product->price * $item['qty'], 2),
                'details' => ['calories' => $product->calories],
            ];
        });
    }
}
