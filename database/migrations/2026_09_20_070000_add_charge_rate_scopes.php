<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rates that vary by building or by size of home.
 *
 * A society with one rate for the whole place was the only case the schema
 * really handled: anything else meant an override row on every single flat.
 * In practice a wing with a lift, or a bigger flat, pays more, and a
 * committee should be able to say that once rather than 96 times.
 *
 * Unit level stays in unit_charge_overrides. This adds the two levels in
 * between, and the calculator resolves the most specific that applies.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charge_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_head_id')->constrained()->cascadeOnDelete();

            // Which slice of the society this rate is for.
            $table->enum('scope', ['block', 'configuration'])->default('block');
            $table->foreignId('block_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('configuration', 40)->nullable();

            $table->decimal('rate', 12, 4);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['society_id', 'charge_head_id', 'scope']);
            $table->unique(['charge_head_id', 'block_id'], 'charge_rate_per_block');
            $table->unique(['charge_head_id', 'configuration'], 'charge_rate_per_configuration');
        });

        Schema::table('billing_plans', function (Blueprint $table) {
            /*
             * Paying a year at once for less than twelve months.
             *
             * Nearly every society offers this and none of them could record
             * it, so the discount lived in the treasurer's head and got
             * applied by hand. Stored as a percentage because it survives a
             * rate change, and typed as an amount in the interface because
             * that is how a committee decides it.
             */
            $table->unsignedSmallInteger('advance_periods')->nullable()->after('due_after_days');
            $table->decimal('advance_discount_percent', 5, 2)->nullable()->after('advance_periods');
        });
    }

    public function down(): void
    {
        Schema::table('billing_plans', function (Blueprint $table) {
            $table->dropColumn(['advance_periods', 'advance_discount_percent']);
        });

        Schema::dropIfExists('charge_rates');
    }
};
