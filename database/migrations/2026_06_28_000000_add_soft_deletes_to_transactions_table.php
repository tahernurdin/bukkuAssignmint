<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make transaction deletes reversible (soft deletes) while keeping a
     * DB-enforced "one live transaction per product+date" guarantee.
     *
     * The old [product_id, date] unique index can't coexist with soft deletes:
     * a deleted row would keep reserving that slot. Instead we index a generated
     * `active_flag` that is 1 for live rows and NULL for deleted ones. Because a
     * unique index treats every NULL as distinct (true on MySQL and SQLite),
     * unique (product_id, date, active_flag) blocks a second *live* row per date
     * yet lets any number of deleted rows share it. The flag is derived from
     * deleted_at by the database, so stock SoftDeletes keeps it in sync for free.
     */
    public function up(): void
    {
        // deleted_at first, so the generated column below can reference it.
        Schema::table('transactions', function (Blueprint $table) {
            $table->softDeletes();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->tinyInteger('active_flag')
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN 1 END')
                ->nullable()
                ->after('deleted_at');
        });

        Schema::table('transactions', function (Blueprint $table) {
            // Add the replacement unique index before dropping the old one, so the
            // product_id foreign key is never left without a covering index (its
            // (product_id, date) prefix also serves the WAC chain queries).
            $table->unique(['product_id', 'date', 'active_flag'], 'transactions_live_unique');
            $table->dropUnique(['product_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unique(['product_id', 'date']);
            $table->dropUnique('transactions_live_unique');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('active_flag');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
