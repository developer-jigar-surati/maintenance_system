<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blocks', function (Blueprint $table) {
            /*
             * Where this building sits on the site, as percentages of the
             * plot rather than metres: a committee arranging a plan is
             * matching a drawing by eye, not surveying, and percentages
             * survive being shown at any size on any screen.
             */
            $table->unsignedTinyInteger('plan_x')->nullable()->after('sort_order');
            $table->unsignedTinyInteger('plan_y')->nullable()->after('plan_x');
            $table->unsignedTinyInteger('plan_width')->nullable()->after('plan_y');
            $table->unsignedTinyInteger('plan_height')->nullable()->after('plan_width');
        });

        Schema::table('units', function (Blueprint $table) {
            // Left-to-right order along a floor, so a plan reads like the
            // corridor does. Null falls back to the unit number.
            $table->unsignedSmallInteger('plan_position')->nullable()->after('floor');
        });

        // Landmarks that are not buildings: the gate, the garden, parking,
        // the clubhouse. A plan without them is hard to orient on.
        Schema::create('site_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->enum('kind', [
                'gate', 'parking', 'garden', 'clubhouse', 'pool', 'playground',
                'temple', 'sports', 'utility', 'road', 'other',
            ])->default('other');
            $table->unsignedTinyInteger('plan_x')->default(0);
            $table->unsignedTinyInteger('plan_y')->default(0);
            $table->unsignedTinyInteger('plan_width')->default(10);
            $table->unsignedTinyInteger('plan_height')->default(10);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['society_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_features');

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('plan_position');
        });

        Schema::table('blocks', function (Blueprint $table) {
            $table->dropColumn(['plan_x', 'plan_y', 'plan_width', 'plan_height']);
        });
    }
};
