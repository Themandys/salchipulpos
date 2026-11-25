<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    private const array Dips = ['Ranch', 'Buffalo', 'Mango Habanero', 'BBQ Ahumada', 'Chipotle'];

    private const array Sauces = ['Buffalo', 'Mango Habanero', 'BBQ Ahumada', 'Lemon Pepper'];

    private const array Drinks = ['Refresco', 'Té helado'];

    private const array Pulpos = ['Salchipulpos Clásicos', 'Salchi-Q BBQ', 'Spicy Pulpo', 'Deluxe Salchipulpos'];

    public function run(): void
    {
        $this->import();
    }

    public function launch(): void
    {
        $this->import(upgrade: true);
    }

    private function import(bool $upgrade = false): void
    {
        foreach ($this->catalog() as $categoryName => $items) {
            $category = Category::query()->firstOrCreate(['name' => $categoryName]);

            foreach ($items as $code => [$name, $price, $calories, $description, $emoji, $options, $formerName]) {
                $product = Product::query()->where('menu_code', $code)->first();

                if ($product && ! $upgrade) {
                    continue;
                }

                $product ??= Product::query()->whereNull('menu_code')
                    ->where('name', $formerName ?? $name)->orderBy('id')->first() ?? new Product;

                $product->fill([
                    'menu_code' => $code,
                    'category_id' => $category->id,
                    'name' => $name,
                    'price' => $price,
                    'calories' => $calories,
                    'description' => $description,
                    'emoji' => $emoji,
                    'is_addon' => $categoryName === 'Extras & Toppings',
                    'menu_options' => $options,
                ]);

                if (! $product->exists) {
                    $product->stock = 50;
                    $product->is_active = true;
                }

                $product->save();
            }
        }
    }

    private function catalog(): array
    {
        $plate = fn (array $choices = []) => ['choices' => $choices, 'combo' => true];
        $bundle = fn (array $choices) => ['choices' => $choices + ['Bebida (355 ml)' => self::Drinks], 'includes_drink' => true];

        return [
            'Crunchy Tenders & Bites' => [
                'tenders-classic' => ['B-Tenders Clásicos (4 pzas + papas)', 95, 780, '4 tiras de pechuga extra crujientes, papas a la francesa y 1 dip a elegir.', '🍗', $plate(['Dip' => self::Dips]), null],
                'tenders-sauced' => ['B-Tenders Bañados (4 pzas + papas)', 105, 840, '4 tiras bañadas en Buffalo, Mango Habanero, BBQ Ahumada o Lemon Pepper, papas y dip ranch.', '🍗', $plate(['Salsa' => self::Sauces]), null],
                'crunch-bites' => ['Crunch Bites / Boneless (200 g + papas)', 95, 810, 'Trozos de pechuga crujiente bañados en tu salsa favorita y papas.', '🍗', $plate(['Salsa' => self::Sauces]), null],
            ],
            'Crispy Chicken Sandwiches' => [
                'sandwich-classic' => ['B-Crunch Classic Sandwich (+ papas)', 95, 790, 'Pechuga crujiente en pan brioche con mantequilla, pepinillos y mayonesa de ajo-chipotle, con papas chicas.', '🍔', $plate(), null],
                'sandwich-spicy' => ['Spicy Crunch Sandwich (+ papas)', 105, 860, 'Pechuga glaseada picante, coleslaw cremoso, queso americano y jalapeños, con papas chicas.', '🍔', $plate(['Salsa' => ['Buffalo', 'Mango Habanero']]), null],
                'sandwich-deluxe' => ['Deluxe Cheddar & Bacon Sandwich (+ papas)', 115, 960, 'Pechuga crujiente, doble cheddar, tocino crocante, aros de cebolla y BBQ-ranch en pan brioche, con papas chicas.', '🍔', $plate(), null],
            ],
            'Salchipulpos (Clásicos de la casa)' => [
                'pulpo-classic' => ['Salchipulpos Clásicos', 55, 580, 'Salchichas doradas corte pulpo, papas a la francesa, cátsup y aderezo especial.', '🐙', $plate(), null],
                'pulpo-bbq' => ['Salchi-Q BBQ', 65, 680, 'Salchipulpos y papas crujientes con salsa BBQ ahumada y aros de cebolla dorados.', '🐙', $plate(), null],
                'pulpo-spicy' => ['Spicy Pulpo', 65, 610, 'Salchipulpos con mezcla picante de la casa, mayonesa-chipotle y limón.', '🌶️', $plate(), null],
                'pulpo-deluxe' => ['Deluxe Salchipulpos', 70, 750, 'Pulpos y papas cubiertos en queso cheddar caliente, tocino crocante y jalapeños.', '🐙', $plate(), null],
                'pulpo-combo' => ['Combo Pulpo Estudiante', 80, 850, 'Salchipulpos a elegir con refresco o té helado frío (355 ml).', '🥤', $bundle(['Salchipulpos' => self::Pulpos]), 'Combo Estudiante'],
            ],
            'Snacks Crujientes & Combos' => [
                'fries-loaded' => ['Papas Crunch Loaded', 65, 720, 'Papas frescas de doble fritura con cheddar caliente, tocino crocante y chipotle.', '🍟', $plate(), null],
                'onion-rings' => ['Aros de Cebolla Crunch (8 pzas)', 55, 480, 'Aros empanizados extra dorados con aderezo ranch o BBQ.', '🧅', $plate(['Dip' => ['Ranch', 'BBQ Ahumada']]), null],
                'mozzarella' => ['Dedos de Queso Mozzarella (5 pzas)', 75, 540, 'Crujientes por fuera y derretidos por dentro, con marinara o ranch.', '🧀', $plate(['Dip' => ['Marinara', 'Ranch']]), null],
                'b-crunch-combo' => ['Combo B-Crunch', 115, 940, 'B-Tenders Clásicos o B-Crunch Classic Sandwich, papas y bebida fría (355 ml).', '🥤', $bundle(['Platillo' => ['B-Tenders Clásicos', 'B-Crunch Classic Sandwich']]), null],
                'crunch-box' => ['Crunch Box Para Compartir', 165, 1450, '3 tenders, 3 salchipulpos, papas grandes, 4 aros de cebolla y 2 dips.', '🍟', $plate(['Primer dip' => self::Dips, 'Segundo dip' => self::Dips]), null],
            ],
            'Extras & Toppings' => [
                'extra-cheddar' => ['Queso Cheddar Líquido (60 g)', 20, 150, 'Cheddar caliente para bañar tu platillo.', '🧀', [], 'Queso Cheddar (50 g)'],
                'extra-bacon' => ['Tocino Picado Crocante (30 g)', 22, 140, 'Tocino crujiente picado.', '🥓', [], 'Tocino Picado (25 g)'],
                'extra-onion' => ['Aros de Cebolla Extra (4 pzas)', 25, 160, 'Cuatro aros de cebolla dorados.', '🧅', [], 'Aros de Cebolla (3 pzas)'],
                'extra-jalapeno' => ['Chiles Jalapeños en rodajas (40 g)', 15, 15, 'Jalapeños en rodajas para añadir picor.', '🌶️', [], 'Jalapeños en rodajas'],
                'extra-dip' => ['Dip / Salsa de la Casa Extra', 15, 90, 'Elige tu dip o salsa de la casa.', '🥣', ['choices' => ['Salsa' => self::Dips]], null],
                'drink-355' => ['Refresco o Té Helado (355 ml)', 25, 150, 'Refresco o té helado bien frío.', '🥤', ['choices' => ['Bebida' => self::Drinks]], null],
            ],
        ];
    }
}
