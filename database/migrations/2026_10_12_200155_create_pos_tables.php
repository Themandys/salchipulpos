<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Ajustes del negocio
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
        });

        // 2. Categorías
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 3. Productos / Catálogo
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->string('name');
            $table->decimal('price', 8, 2)->default(0.00);
            $table->integer('stock')->default(0);
            $table->string('emoji', 16)->default('🍽️');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 4. Ventas (con método de pago: efectivo / tarjeta)
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('folio')->unique();
            $table->decimal('total', 8, 2)->default(0.00);
            $table->decimal('paid', 8, 2)->default(0.00);
            $table->decimal('change', 8, 2)->default(0.00);
            $table->string('payment_method', 20)->default('efectivo');
            $table->timestamps();
        });

        // 5. Detalle de venta por ticket
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_name');
            $table->decimal('price', 8, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('subtotal', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('settings');
    }
};
