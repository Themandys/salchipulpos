<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('settings')->insertOrIgnore([
            ['key' => 'business_pin', 'value' => '1234'],
            ['key' => 'business_name', 'value' => 'B-CRUNCH'],
            ['key' => 'ticket_footer', 'value' => '¡Gracias por tu compra!'],
            ['key' => 'opening_cash', 'value' => '500'],
        ]);
    }
}
