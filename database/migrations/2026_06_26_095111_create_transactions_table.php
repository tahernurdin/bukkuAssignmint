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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // 'purchase' or 'sale'
            $table->date('date');
            $table->decimal('quantity', 15, 2);
            $table->decimal('price', 15, 2);
            $table->decimal('calculated_cost', 15, 2)->nullable();
            $table->decimal('wac_at_time', 15, 2)->nullable();
            $table->decimal('quantity_on_hand', 15, 2)->nullable();
            $table->decimal('value_on_hand', 15, 2)->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
