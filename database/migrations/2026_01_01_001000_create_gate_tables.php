<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->enum('type', ['main', 'service', 'pedestrian', 'parking', 'emergency'])->default('main');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['society_id', 'name']);
        });

        // A person known to the gate. Frequent visitors are remembered so the
        // guard does not re-key their details on every visit.
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('photo_path')->nullable();
            $table->string('id_proof_type', 40)->nullable();
            $table->string('id_proof_number', 40)->nullable();
            $table->string('company')->nullable();
            $table->boolean('is_frequent')->default(false);
            $table->boolean('is_blacklisted')->default(false);
            $table->string('blacklist_reason')->nullable();
            $table->timestamps();

            $table->index(['society_id', 'phone']);
        });

        // One visit. Covers pre-approval by the resident, guard check-in and exit.
        Schema::create('visitor_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visitor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('gate_id')->nullable()->constrained()->nullOnDelete();

            $table->string('visitor_name');
            $table->string('phone', 20)->nullable();
            $table->enum('purpose', [
                'guest', 'delivery', 'cab', 'service', 'vendor',
                'staff', 'courier', 'interview', 'other',
            ])->default('guest');
            $table->string('company')->nullable();
            $table->unsignedSmallInteger('accompanying_count')->default(0);
            $table->string('vehicle_number', 20)->nullable();

            // A short code the visitor quotes at the gate; also encoded as a QR.
            $table->string('pass_code', 12)->nullable();
            $table->dateTime('expected_at')->nullable();
            $table->dateTime('expected_until')->nullable();
            $table->foreignId('pre_approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('status', [
                'expected', 'pending_approval', 'approved', 'denied',
                'inside', 'exited', 'expired',
            ])->default('pending_approval');

            $table->timestamp('entered_at')->nullable();
            $table->timestamp('exited_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['society_id', 'status']);
            $table->index(['society_id', 'entered_at']);
            $table->index(['society_id', 'pass_code']);
        });

        // Material movement and move-in/move-out authorisations, QR verifiable.
        Schema::create('gate_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pass_number', 40);
            $table->enum('type', [
                'material_in', 'material_out', 'move_in', 'move_out',
                'vehicle', 'visitor', 'contractor',
            ])->default('material_out');
            $table->string('qr_token', 64)->unique();
            $table->string('issued_to_name');
            $table->string('phone', 20)->nullable();
            $table->string('vehicle_number', 20)->nullable();
            $table->json('items')->nullable();
            $table->text('purpose')->nullable();
            $table->dateTime('valid_from');
            $table->dateTime('valid_to');
            $table->enum('status', ['pending_approval', 'approved', 'rejected', 'used', 'expired', 'cancelled'])
                ->default('pending_approval');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['society_id', 'pass_number']);
            $table->index(['society_id', 'status']);
        });

        Schema::create('sos_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['medical', 'fire', 'security', 'accident', 'other'])->default('other');
            $table->text('message')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->enum('status', ['active', 'acknowledged', 'resolved', 'false_alarm'])->default('active');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['society_id', 'status']);
        });

        Schema::create('emergency_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('phone', 20);
            $table->string('alternate_phone', 20)->nullable();
            $table->enum('category', [
                'police', 'fire', 'ambulance', 'hospital', 'security',
                'management', 'plumber', 'electrician', 'lift_service', 'gas', 'other',
            ])->default('other');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['society_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emergency_contacts');
        Schema::dropIfExists('sos_alerts');
        Schema::dropIfExists('gate_passes');
        Schema::dropIfExists('visitor_logs');
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('gates');
    }
};
