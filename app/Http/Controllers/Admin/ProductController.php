<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    /**
     * Agrega un producto al catálogo.
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::query()->create($this->attributes($request->validated()));

        return response()->json(['product' => $product->load('category:id,name')], 201);
    }

    /**
     * Actualiza nombre, categoría, precio, stock o disponibilidad.
     */
    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($this->attributes($request->validated()));

        return response()->json(['product' => $product->load('category:id,name')]);
    }

    /**
     * Retira el producto del catálogo sin perder el historial de ventas.
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->update(['is_active' => false]);

        return response()->json(['product' => $product]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        if (array_key_exists('category', $validated)) {
            $validated['category_id'] = Category::query()->firstOrCreate(['name' => trim($validated['category'])])->id;
            unset($validated['category']);
        }

        if (array_key_exists('emoji', $validated) && blank($validated['emoji'])) {
            $validated['emoji'] = '🍽️';
        }

        return $validated;
    }
}
