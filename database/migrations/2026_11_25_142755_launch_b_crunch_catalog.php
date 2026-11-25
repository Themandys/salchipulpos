<?php

use Database\Seeders\MenuSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('products')->exists() || DB::table('settings')->exists()) {
            (new MenuSeeder)->launch();
        }

        DB::table('settings')->updateOrInsert(['key' => 'business_name'], ['value' => 'B-CRUNCH']);
    }

    public function down(): void
    {
        // An earlier stock or price cannot be reconstructed from the new catalog.
    }
};
