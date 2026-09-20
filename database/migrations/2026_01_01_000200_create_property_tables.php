<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A grouping inside a society: wing, tower, phase, street, sector.
        // Optional, because a small society may have a flat unit list.
        Schema::create('blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->enum('kind', ['wing', 'tower', 'block', 'phase', 'street', 'sector', 'floor_group'])->default('wing');
            $table->unsignedSmallInteger('floor_count')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['society_id', 'name']);
        });

        // The billable object. "Unit" covers a flat, villa, plot, shop or office.
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit_number', 40);
            $table->string('floor', 20)->nullable();

            $table->enum('type', [
                'flat', 'villa', 'row_house', 'bungalow', 'plot', 'studio', 'penthouse',
                'duplex', 'shop', 'office', 'showroom', 'warehouse', 'parking', 'other',
            ])->default('flat');

            $table->string('configuration', 20)->nullable(); // 2BHK, 3BHK, etc.
            $table->unsignedTinyInteger('bedrooms')->nullable();

            // Area drives per-sqft billing; carpet area is the usual basis.
            $table->decimal('carpet_area', 10, 2)->nullable();
            $table->decimal('built_up_area', 10, 2)->nullable();
            $table->decimal('super_built_up_area', 10, 2)->nullable();

            $table->enum('occupancy_status', ['owner_occupied', 'rented', 'vacant', 'under_construction', 'locked'])
                ->default('vacant');
            $table->date('possession_date')->nullable();

            // Carried in from the previous system or from paper books.
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opening_balance_as_on')->nullable();

            // Excludes the unit from automatic bill runs (e.g. builder-held stock).
            $table->boolean('is_billable')->default(true);
            $table->text('notes')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['society_id', 'block_id', 'unit_number']);
            $table->index(['society_id', 'occupancy_status']);
            $table->index(['society_id', 'type']);
        });

        // Who lives in / owns a unit, and for what period. A unit can have an
        // owner and a tenant at once; history is kept by closing a row.
        Schema::create('unit_residents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->enum('relation', ['owner', 'co_owner', 'tenant', 'family_member', 'occupant'])->default('owner');

            // The person shown as the unit's representative in listings.
            $table->boolean('is_primary')->default(false);
            // The person invoices and receipts are addressed to.
            $table->boolean('is_billing_contact')->default(false);

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // Tenancy paperwork, replacing the old rent-agreement fields.
            $table->date('agreement_start_date')->nullable();
            $table->date('agreement_end_date')->nullable();
            $table->decimal('rent_amount', 12, 2)->nullable();
            $table->decimal('deposit_amount', 12, 2)->nullable();
            $table->string('agreement_document_path')->nullable();
            $table->boolean('police_verification_done')->default(false);

            $table->enum('status', ['active', 'ended'])->default('active');
            $table->timestamps();

            $table->index(['society_id', 'unit_id', 'status']);
            $table->index(['society_id', 'user_id']);
        });

        Schema::create('parking_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 30);
            $table->enum('level', ['basement', 'stilt', 'ground', 'open', 'covered', 'multi_level'])->default('open');
            $table->enum('vehicle_type', ['car', 'two_wheeler', 'any', 'ev', 'oversized'])->default('any');
            $table->boolean('is_visitor_slot')->default(false);
            $table->decimal('monthly_charge', 10, 2)->default(0);
            $table->enum('status', ['vacant', 'allotted', 'reserved', 'blocked'])->default('vacant');
            $table->date('allotted_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['society_id', 'code']);
            $table->index(['society_id', 'status']);
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parking_slot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('registration_number', 20);
            $table->enum('type', ['car', 'two_wheeler', 'bicycle', 'commercial', 'ev', 'other'])->default('car');
            $table->string('make_model')->nullable();
            $table->string('colour', 30)->nullable();
            $table->string('sticker_number', 30)->nullable();
            $table->date('insurance_expires_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['society_id', 'registration_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('parking_slots');
        Schema::dropIfExists('unit_residents');
        Schema::dropIfExists('units');
        Schema::dropIfExists('blocks');
    }
};
