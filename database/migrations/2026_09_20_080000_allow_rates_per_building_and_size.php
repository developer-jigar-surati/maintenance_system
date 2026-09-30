<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A rate can name a building and a size together.
 *
 * The first cut treated the two as alternatives: either every building has
 * its own amount, or every size does. Real societies do both at once. One
 * has A at 12,000, B at 11,000 and the GHI block at 8,000, and inside each
 * of those the 2BHK and the 3BHK differ again.
 *
 * Forcing that into "by building" loses the size, and into "by size" loses
 * the building. So a rate may now carry both, and the calculator prefers the
 * pair over either half of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE charge_rates MODIFY COLUMN scope
             ENUM('block', 'configuration', 'block_configuration') NOT NULL DEFAULT 'block'"
        );

        /*
         * The new index goes on first, then the old ones come off.
         *
         * Both share charge_head_id as their leading column, which is the
         * index the foreign key relies on. Drop first and MariaDB refuses,
         * because for a moment the constraint would have nothing to stand on.
         */
        Schema::table('charge_rates', function (Blueprint $table) {
            $table->unique(
                ['charge_head_id', 'scope', 'block_id', 'configuration'],
                'charge_rate_slice'
            );
        });

        /*
         * Dropped by name only where they exist: MariaDB folds a unique index
         * into a foreign key's index when the columns line up, so one of the
         * two is not always there to drop.
         */
        foreach (['charge_rate_per_block', 'charge_rate_per_configuration'] as $index) {
            if (self::hasIndex('charge_rates', $index)) {
                DB::statement("ALTER TABLE charge_rates DROP INDEX `{$index}`");
            }
        }
    }

    private static function hasIndex(string $table, string $index): bool
    {
        return DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index],
        ) !== [];
    }

    public function down(): void
    {
        if (self::hasIndex('charge_rates', 'charge_rate_slice')) {
            DB::statement('ALTER TABLE charge_rates DROP INDEX `charge_rate_slice`');
        }

        Schema::table('charge_rates', function (Blueprint $table) {
            $table->unique(['charge_head_id', 'block_id'], 'charge_rate_per_block');
            $table->unique(['charge_head_id', 'configuration'], 'charge_rate_per_configuration');
        });

        DB::statement(
            "ALTER TABLE charge_rates MODIFY COLUMN scope
             ENUM('block', 'configuration') NOT NULL DEFAULT 'block'"
        );
    }
};
