<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PosSeeder extends Seeder
{
    public function run(): void
    {
        // Configuración inicial del negocio (no sobrescribe lo que ya se cambió en Ajustes)
        DB::table('settings')->insertOrIgnore([
            ['key' => 'business_pin', 'value' => '1234'],
            ['key' => 'business_name', 'value' => 'CUIDADO CON EL SALCHIPULPO'],
            ['key' => 'ticket_footer', 'value' => '¡Gracias por tu compra!'],
            ['key' => 'opening_cash', 'value' => '500'],
        ]);

        if (DB::table('products')->exists()) {
            return;
        }

        // Categorías validadas en el estudio de mercado
        $catPulpos = DB::table('categories')->insertGetId([
            'name' => 'Salchipulpos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $catCombos = DB::table('categories')->insertGetId([
            'name' => 'Combos',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $catSnacks = DB::table('categories')->insertGetId([
            'name' => 'Snacks & Extras',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $catBebidas = DB::table('categories')->insertGetId([
            'name' => 'Bebidas',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Catálogo de productos con precios y complementos ganadores (>50% aprobación)
        DB::table('products')->insert([
            // Salchipulpos
            [
                'category_id' => $catPulpos,
                'name' => 'Salchipulpos Tradicionales',
                'price' => 35.00,
                'stock' => 50,
                'emoji' => '🐙',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_id' => $catPulpos,
                'name' => 'Salchipulpos con Papas',
                'price' => 50.00,
                'stock' => 50,
                'emoji' => '🍟',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Combos universitarios
            [
                'category_id' => $catCombos,
                'name' => 'Combo Pulpo (Pulpos + Papas + Bebida)',
                'price' => 65.00,
                'stock' => 40,
                'emoji' => '🔥',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_id' => $catCombos,
                'name' => 'Combo Banderilla (Banderilla + Papas + Bebida)',
                'price' => 60.00,
                'stock' => 40,
                'emoji' => '🌭',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Complementos ganadores (Papas: 75.8% | Banderillas: 68.1%)
            [
                'category_id' => $catSnacks,
                'name' => 'Banderilla Clásica',
                'price' => 25.00,
                'stock' => 45,
                'emoji' => '🌭',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_id' => $catSnacks,
                'name' => 'Orden de Papas a la Francesa',
                'price' => 30.00,
                'stock' => 50,
                'emoji' => '🍟',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_id' => $catSnacks,
                'name' => 'Queso extra derretido',
                'price' => 10.00,
                'stock' => 60,
                'emoji' => '🧀',
                'created_at' => now(),
                'updated_at' => now(),
            ],

            // Bebidas para venta individual y combos
            [
                'category_id' => $catBebidas,
                'name' => 'Refresco embotellado',
                'price' => 20.00,
                'stock' => 60,
                'emoji' => '🥤',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'category_id' => $catBebidas,
                'name' => 'Agua embotellada',
                'price' => 15.00,
                'stock' => 60,
                'emoji' => '💧',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
