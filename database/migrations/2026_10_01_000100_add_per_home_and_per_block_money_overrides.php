<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two exceptions a committee actually makes by hand.
 *
 * A flat settled individually, and a building offered a different deal for
 * paying the year up front. Both were decided in meetings long before this
 * software existed; until now neither had anywhere to live except the
 * treasurer's memory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_charge_overrides', function (Blueprint $table) {
            // Who agreed to it. An amount set for one flat is the kind of
            // thing that gets questioned a year later, and "nobody knows who
            // did this" is the answer that costs a committee its credibility.
            $table->foreignId('set_by')->nullable()->after('is_exempt')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('advance_discounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_plan_id')->constrained()->cascadeOnDelete();

            // The deal for one building. The plan carries the society's own
            // figure, so a row here is only ever an exception to it.
            $table->foreignId('block_id')->constrained()->cascadeOnDelete();

            // Stored as a percentage rather than an amount, so it keeps
            // working when the building's maintenance changes and when the
            // flats inside it pay different amounts by size.
            $table->decimal('discount_percent', 5, 2);
            $table->timestamps();

            $table->unique(['billing_plan_id', 'block_id'], 'advance_discount_per_block');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_discounts');

        Schema::table('unit_charge_overrides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('set_by');
        });
    }
};
