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
        Schema::create('cash_cuts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10);
            $table->dateTime('period_start');
            $table->dateTime('period_end');
            $table->unsignedInteger('sales_count')->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('cash_total', 10, 2)->default(0);
            $table->decimal('card_total', 10, 2)->default(0);
            $table->decimal('opening_cash', 10, 2)->nullable();
            $table->decimal('counted_cash', 10, 2)->nullable();
            $table->json('summary')->nullable();
            $table->dateTime('closed_at');
            $table->timestamps();

            $table->unique(['type', 'period_start']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::dropIfExists('cash_cuts');
    }
};
