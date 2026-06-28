<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Each row is both the record of a single-product purchase/sale AND a snapshot
     * of the inventory state immediately *after* that transaction. Storing the
     * running snapshot per row is what lets us (a) return costing info cheaply and
     * (b) recalculate efficiently from any point when transactions are inserted,
     * updated, or deleted out of date order.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // App\Enums\TransactionType: 'purchase' | 'sale'
            $table->date('date');

            // Transaction inputs. Quantity is fractional-capable; buying_price is
            // the unit purchase cost and is set on purchases only — a sale carries
            // no price, its cost of goods sold is derived from the running WAC.
            $table->decimal('quantity', 15, 2);
            $table->decimal('buying_price', 15, 2)->nullable();

            // WAC snapshot AFTER this transaction. High precision (6 dp) so the
            // running average does not drift over a long chain of transactions;
            // callers round to 2 dp for display.
            $table->decimal('calculated_cost', 20, 6)->nullable(); // cost of goods sold (sales only)
            $table->decimal('wac_at_time', 20, 6)->nullable();     // average unit cost after this row
            $table->decimal('quantity_on_hand', 15, 2)->nullable();
            $table->decimal('value_on_hand', 20, 6)->nullable();

            $table->timestamps();

            // One transaction per product per date (each product is its own ledger).
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
