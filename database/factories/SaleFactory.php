<?php

namespace Database\Factories;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->randomElement([35, 50, 65, 100]);

        return [
            'folio' => fake()->unique()->numberBetween(1, 999999),
            'total' => $total,
            'paid' => $total,
            'change' => 0,
            'payment_method' => Sale::MethodCash,
        ];
    }

    /**
     * Sale paid by card.
     */
    public function card(): static
    {
        return $this->state(fn (array $attributes) => ['payment_method' => Sale::MethodCard]);
    }
}
