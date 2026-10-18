<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('description', 255)->nullable()->after('name');
            $table->unsignedInteger('calories')->nullable()->after('price');
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('customer_name', 60)->nullable();
            $table->string('note', 200)->nullable();
            $table->string('status', 12)->default('pending')->index();
            $table->json('items');
            $table->decimal('total', 10, 2)->default(0);
            $table->foreignId('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['description', 'calories']);
        });
    }
};
