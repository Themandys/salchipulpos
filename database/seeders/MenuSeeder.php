<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    public const string MainCategory = 'Menú principal';

    public const string ExtrasCategory = 'Extras & Toppings';

    /**
     * The official menu. Products that are not on it are hidden (never deleted).
     *
     * @var array<string, list<array{name: string, description: string, price: int, calories: int, emoji: string}>>
     */
    public const array Menu = [
        self::MainCategory => [
            ['name' => 'Salchipulpos Clásicos', 'description' => 'Salchichas doradas corte pulpo + papas a la francesa, cátsup y aderezo especial de la casa.', 'price' => 50, 'calories' => 580, 'emoji' => '🐙'],
            ['name' => 'Salchi-Q BBQ', 'description' => 'Salchipulpos y papas crujientes bañados en salsa BBQ ahumada y aros de cebolla dorados.', 'price' => 55, 'calories' => 680, 'emoji' => '🔥'],
            ['name' => 'Spicy Pulpo', 'description' => 'Salchipulpos sazonados con mezcla picante de la casa, aderezo de mayonesa-chipotle y limón.', 'price' => 55, 'calories' => 610, 'emoji' => '🌶️'],
            ['name' => 'Deluxe Salchipulpos', 'description' => 'Nuestra especialidad: pulpos y papas cubiertos en queso cheddar caliente, tocino crocante y jalapeños.', 'price' => 70, 'calories' => 750, 'emoji' => '⭐'],
            ['name' => 'Combo Estudiante', 'description' => 'Salchipulpos a elegir + refresco o té helado bien frío (355 ml).', 'price' => 80, 'calories' => 850, 'emoji' => '🎒'],
        ],
        self::ExtrasCategory => [
            ['name' => 'Queso Cheddar (50 g)', 'description' => 'Queso cheddar caliente para bañar tus salchipulpos.', 'price' => 10, 'calories' => 140, 'emoji' => '🧀'],
            ['name' => 'Tocino Picado (25 g)', 'description' => 'Tocino crocante picado por encima.', 'price' => 12, 'calories' => 130, 'emoji' => '🥓'],
            ['name' => 'Aros de Cebolla (3 pzas)', 'description' => 'Tres aros de cebolla dorados y crujientes.', 'price' => 15, 'calories' => 120, 'emoji' => '🧅'],
            ['name' => 'Jalapeños en rodajas', 'description' => 'Para los que aguantan el picor.', 'price' => 5, 'calories' => 10, 'emoji' => '🌶️'],
        ],
    ];

    /**
     * Load the menu into the catalog, keeping the stock of products that already exist.
     */
    public function run(): void
    {
        $menuIds = [];

        foreach (self::Menu as $categoryName => $items) {
            $category = Category::query()->firstOrCreate(['name' => $categoryName]);

            foreach ($items as $item) {
                $product = Product::query()->firstOrNew(['name' => $item['name']]);
                $product->fill([...$item, 'category_id' => $category->id, 'is_active' => true]);
                $product->stock ??= 50;
                $product->save();

                $menuIds[] = $product->id;
            }
        }

        Product::query()->whereNotIn('id', $menuIds)->update(['is_active' => false]);
    }
}
