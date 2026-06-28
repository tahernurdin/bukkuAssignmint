<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make product deletes reversible (soft deletes) while keeping a DB-enforced
     * "one live product per sku" guarantee — the same generated-flag trick used
     * for transactions (see that migration for the full rationale).
     *
     * unique (sku, active_flag) blocks a second *live* product per sku, but a
     * deleted product's flag is NULL (distinct), so the sku frees up for reuse.
     * Soft-deleting a product also stops its delete from firing the product_id
     * cascade, so the product's transaction history survives.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->tinyInteger('active_flag')
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN 1 END')
                ->nullable()
                ->after('deleted_at');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['sku', 'active_flag'], 'products_sku_live_unique');
            $table->dropUnique(['sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unique('sku');
            $table->dropUnique('products_sku_live_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('active_flag');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
