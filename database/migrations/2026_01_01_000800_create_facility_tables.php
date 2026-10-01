<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Physical equipment the society owns and must keep running.
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('block_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 40)->nullable();
            $table->enum('category', [
                'lift', 'generator', 'water_pump', 'fire_safety', 'cctv', 'solar',
                'stp', 'wtp', 'gym_equipment', 'playground', 'hvac', 'electrical',
                'plumbing', 'vehicle', 'furniture', 'it_equipment', 'other',
            ])->default('other');
            $table->string('location')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_cost', 14, 2)->nullable();
            $table->date('warranty_expires_on')->nullable();
            $table->decimal('depreciation_rate', 5, 2)->nullable();
            $table->enum('condition', ['excellent', 'good', 'fair', 'poor'])->default('good');
            $table->enum('status', ['active', 'under_repair', 'idle', 'retired', 'disposed'])->default('active');
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['society_id', 'code']);
            $table->index(['society_id', 'category', 'status']);
        });

        // Annual maintenance contracts, with expiry reminders.
        Schema::create('amc_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('contract_number', 40)->nullable();
            $table->enum('type', ['amc', 'cmc', 'warranty', 'service', 'insurance'])->default('amc');
            $table->string('title');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('amount', 14, 2)->default(0);
            $table->enum('payment_frequency', ['one_time', 'monthly', 'quarterly', 'half_yearly', 'yearly'])
                ->default('yearly');
            $table->unsignedSmallInteger('reminder_days_before')->default(30);
            $table->timestamp('reminder_sent_at')->nullable();
            $table->text('scope_of_work')->nullable();
            $table->string('document_path')->nullable();
            $table->enum('status', ['active', 'expired', 'terminated', 'renewed'])->default('active');
            $table->timestamps();

            $table->index(['society_id', 'status', 'end_date']);
        });

        // Preventive maintenance: recurring checks that create work orders.
        Schema::create('maintenance_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('frequency', [
                'daily', 'weekly', 'fortnightly', 'monthly',
                'quarterly', 'half_yearly', 'yearly', 'custom_days',
            ])->default('monthly');
            $table->unsignedSmallInteger('interval_days')->nullable();
            $table->date('starts_on');
            $table->date('next_due_on')->nullable();
            $table->date('last_completed_on')->nullable();
            // Steps the technician ticks off, stored as an ordered list.
            $table->json('checklist')->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->boolean('auto_create_work_order')->default(true);
            $table->unsignedSmallInteger('create_days_before')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['society_id', 'is_active', 'next_due_on']);
        });

        // A unit of work: raised from a complaint, a PM schedule, or by hand.
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('work_order_number', 40);
            $table->enum('source', ['manual', 'complaint', 'schedule', 'inspection'])->default('manual');
            $table->foreignId('complaint_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_schedule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('status', [
                'open', 'assigned', 'in_progress', 'on_hold',
                'completed', 'verified', 'cancelled',
            ])->default('open');

            $table->date('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();

            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->json('checklist')->nullable();
            $table->text('completion_notes')->nullable();
            $table->json('attachments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['society_id', 'work_order_number']);
            $table->index(['society_id', 'status', 'priority']);
        });

        Schema::create('amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', [
                'clubhouse', 'party_hall', 'banquet', 'gym', 'swimming_pool',
                'tennis_court', 'badminton_court', 'basketball_court', 'guest_room',
                'community_hall', 'garden', 'bbq_area', 'library', 'co_working',
                'terrace', 'amphitheatre', 'other',
            ])->default('other');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('photo_path')->nullable();

            $table->boolean('is_bookable')->default(true);
            $table->decimal('charge_amount', 12, 2)->default(0);
            $table->enum('charge_basis', ['free', 'per_hour', 'per_slot', 'per_day', 'per_person'])->default('free');
            $table->decimal('deposit_amount', 12, 2)->default(0);

            // Booking guard-rails.
            $table->unsignedSmallInteger('min_booking_minutes')->default(60);
            $table->unsignedSmallInteger('max_booking_minutes')->default(480);
            $table->unsignedSmallInteger('advance_booking_days')->default(30);
            $table->unsignedSmallInteger('min_notice_hours')->default(2);
            $table->unsignedTinyInteger('max_active_bookings_per_unit')->default(2);
            $table->boolean('requires_approval')->default(true);
            // Charges land on the unit's next bill instead of being paid upfront.
            $table->boolean('bill_to_unit')->default(true);

            $table->time('opens_at')->nullable();
            $table->time('closes_at')->nullable();
            $table->json('available_days')->nullable(); // [1..7], ISO weekday numbers
            $table->text('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['society_id', 'is_active']);
        });

        Schema::create('amenity_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('booking_number', 40);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->unsignedInteger('guests_count')->default(0);
            $table->string('purpose')->nullable();
            $table->decimal('charge_amount', 12, 2)->default(0);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->enum('status', [
                'pending', 'approved', 'rejected', 'cancelled', 'completed', 'no_show',
            ])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('cancellation_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['society_id', 'booking_number']);
            // Overlap checks scan this range for the amenity.
            $table->index(['amenity_id', 'starts_at', 'ends_at']);
            $table->index(['society_id', 'status']);
        });

        // Periods where an amenity cannot be booked (repairs, society events).
        Schema::create('amenity_blackouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('amenity_id')->constrained()->cascadeOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['amenity_id', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amenity_blackouts');
        Schema::dropIfExists('amenity_bookings');
        Schema::dropIfExists('amenities');
        Schema::dropIfExists('work_orders');
        Schema::dropIfExists('maintenance_schedules');
        Schema::dropIfExists('amc_contracts');
        Schema::dropIfExists('assets');
    }
};
