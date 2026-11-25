<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('menu_code', 40)->nullable()->unique();
            $table->boolean('is_addon')->default(false);
            $table->json('menu_options')->nullable();
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->json('details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('details');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['menu_code']);
            $table->dropColumn(['menu_code', 'is_addon', 'menu_options']);
        });
    }
};
