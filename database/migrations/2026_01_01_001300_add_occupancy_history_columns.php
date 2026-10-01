<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('unit_residents', function (Blueprint $table) {
            // Why an occupancy ended, which is the question a committee asks
            // when they look back at a unit that keeps changing hands.
            $table->string('move_out_reason', 160)->nullable()->after('end_date');
            $table->text('handover_notes')->nullable()->after('move_out_reason');
            $table->foreignId('recorded_by')->nullable()->after('handover_notes')
                ->constrained('users')->nullOnDelete();

            $table->index(['society_id', 'unit_id', 'start_date']);
            $table->index(['society_id', 'user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::table('unit_residents', function (Blueprint $table) {
            $table->dropForeign(['recorded_by']);
            $table->dropIndex(['society_id', 'unit_id', 'start_date']);
            $table->dropIndex(['society_id', 'user_id', 'start_date']);
            $table->dropColumn(['move_out_reason', 'handover_notes', 'recorded_by']);
        });
    }
};
