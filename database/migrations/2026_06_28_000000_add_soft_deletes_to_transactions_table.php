<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make transaction deletes reversible (soft deletes).
     *
     * A soft-deleted row stays in the table, so the [product_id, date] *unique*
     * index would keep blocking a fresh transaction for the same product+date.
     * We drop the uniqueness (now enforced only over live rows, in the form
     * requests) but keep a plain composite index, which the WAC engine relies on
     * to walk a product's chain in date order.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add the replacement index first: on MySQL the product_id foreign key
            // leans on the unique index, so dropping it before another index covers
            // product_id would fail ("needed in a foreign key constraint").
            $table->index(['product_id', 'date']);
            $table->dropUnique(['product_id', 'date']);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropSoftDeletes();
            // Restore the unique index before dropping the plain one, so the
            // product_id foreign key is always covered (see up()).
            $table->unique(['product_id', 'date']);
            $table->dropIndex(['product_id', 'date']);
        });
    }
};
