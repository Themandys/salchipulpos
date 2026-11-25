<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class OrderItems
{
    public function price(array $items): Collection
    {
        $ids = collect($items)->flatMap(fn (array $item) => [$item['id'], ...array_column($item['extras'] ?? [], 'id')]);
        $products = Product::query()->active()->where(function ($query) use ($ids) {
            $query->whereKey($ids->unique()->values()->all())->orWhere('menu_code', Product::DrinkCode);
        })->get()->keyBy('id');
        $drink = $products->firstWhere('menu_code', Product::DrinkCode);

        $lines = collect($items)->map(fn (array $item) => $this->priceLine($item, $products, $drink));

        $this->checkStock($this->stockRequirements($lines), $products);

        return $lines;
    }

    private function priceLine(array $item, Collection $products, ?Product $drink): array
    {
        $product = $products->get($item['id']);
        $this->ensure($product && ! $product->is_addon, 'Selecciona un platillo disponible del menú.');

        $options = $product->menu_options ?? [];
        $labels = $this->choices($options['choices'] ?? [], $item['choices'] ?? []);
        $requirements = [];

        if ($options['includes_drink'] ?? false) {
            $this->ensure($drink !== null, 'La bebida incluida en el combo está agotada.');
            $requirements[] = $drink->id;
        }

        $price = $product->price;
        $calories = $product->calories;

        if (isset($item['combo'])) {
            $this->ensure(($options['combo'] ?? false) && $drink, 'Este platillo no se puede convertir en combo ahora.');
            $this->choices($drink->menu_options['choices'] ?? [], ['Bebida' => $item['combo']]);
            $labels[] = 'Hazlo Combo: '.$item['combo'].' (355 ml)';
            $requirements[] = $drink->id;
            $price += $drink->price;
            $calories = $this->addCalories($calories, $drink->calories);
        }

        foreach ($item['extras'] ?? [] as $extra) {
            $addon = $products->get($extra['id']);
            $this->ensure($addon && $addon->is_addon, 'Uno de los extras seleccionados no está disponible.');
            $choices = $this->choices($addon->menu_options['choices'] ?? [], $extra['choices'] ?? []);
            $labels[] = $addon->name.($choices ? ' · '.implode(', ', $choices) : '');
            $requirements[] = $addon->id;
            $price += $addon->price;
            $calories = $this->addCalories($calories, $addon->calories);
        }

        $price = round($price, 2);

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'price' => $price,
            'quantity' => (int) $item['qty'],
            'subtotal' => round($price * $item['qty'], 2),
            'details' => ['labels' => $labels, 'calories' => $calories, 'addons' => $requirements],
        ];
    }

    private function choices(array $groups, array $selected): array
    {
        $this->ensure(! array_diff_key($selected, $groups), 'Este platillo no admite una de las opciones seleccionadas.');
        $labels = [];

        foreach ($groups as $label => $values) {
            $choice = $selected[$label] ?? $values[0];
            $this->ensure(in_array($choice, $values, true), "Selecciona una opción disponible para {$label}.");
            $labels[] = "{$label}: {$choice}";
        }

        return $labels;
    }

    private function addCalories(?int $total, ?int $extra): ?int
    {
        return $total !== null && $extra !== null ? $total + $extra : null;
    }

    public function stockRequirements(Collection $lines): array
    {
        $requirements = [];

        foreach ($lines as $line) {
            foreach ([$line['product_id'], ...($line['details']['addons'] ?? [])] as $id) {
                $requirements[$id] = ($requirements[$id] ?? 0) + $line['quantity'];
            }
        }

        return $requirements;
    }

    public function checkStock(array $requirements, Collection $products): void
    {
        foreach ($requirements as $id => $quantity) {
            $product = $products->get($id);
            $this->ensure($product !== null, 'Uno de los productos ya no está disponible.');
            $this->ensure($product->stock >= $quantity, "Stock insuficiente para \"{$product->name}\". Quedan {$product->stock}.");
        }
    }

    private function ensure(bool $valid, string $message): void
    {
        if (! $valid) {
            throw ValidationException::withMessages(['items' => $message]);
        }
    }
}
