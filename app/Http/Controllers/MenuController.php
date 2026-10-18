<?php

namespace App\Http\Controllers;

use App\Models\Category;
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
            ->with(['products' => fn ($query) => $query->active()->orderBy('id')])
            ->orderBy('id')
            ->get()
            ->filter(fn (Category $category) => $category->products->isNotEmpty())
            ->values();

        return view('menu', [
            'settings' => Setting::publicSettings(),
            'categories' => $categories,
            'menuProducts' => $categories->flatMap->products
                ->map->only(['id', 'name', 'description', 'price', 'calories', 'emoji', 'stock'])
                ->values(),
        ]);
    }
}
