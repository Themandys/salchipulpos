<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

class MenuController extends Controller
{
    /**
     * Menú público: los clientes ven los productos y arman su pedido.
     */
    public function index(): View
    {
        $categories = Category::query()
            ->with(['products' => fn ($query) => $query->active()->where('is_addon', false)->orderBy('id')])
            ->orderBy('id')
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty())
            ->values();

        $addons = Product::query()->active()->where('is_addon', true)->orderBy('id')->get();
        $drink = $addons->firstWhere('menu_code', Product::DrinkCode);
        $fields = ['id', 'name', 'description', 'price', 'calories', 'emoji', 'stock', 'menu_options'];

        return view('menu', [
            'settings' => Setting::publicSettings(),
            'categories' => $categories,
            'menuData' => [
                'products' => $categories->flatMap->products->map->only($fields)->values(),
                'addons' => $addons->map->only($fields)->values(),
                'drinkId' => $drink?->id,
                'ordersUrl' => route('api.orders.store', absolute: false),
            ],
        ]);
    }
}
