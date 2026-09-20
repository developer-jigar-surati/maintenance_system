<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Society payroll and outsourced staff.
        Schema::create('staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            // Set when the staff member also logs in (a manager or supervisor).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('employee_code', 30)->nullable();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->enum('department', [
                'security', 'housekeeping', 'maintenance', 'administration',
                'accounts', 'gardening', 'plumbing', 'electrical', 'other',
            ])->default('other');
            $table->string('designation')->nullable();
            $table->enum('employment_type', ['permanent', 'contract', 'outsourced', 'part_time'])->default('contract');
            $table->date('joined_on')->nullable();
            $table->date('left_on')->nullable();
            $table->decimal('monthly_salary', 12, 2)->nullable();
            $table->string('shift', 40)->nullable();
            $table->string('id_proof_type', 40)->nullable();
            $table->string('id_proof_number', 40)->nullable();
            $table->boolean('police_verified')->default(false);
            $table->date('police_verified_on')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact', 20)->nullable();
            $table->enum('status', ['active', 'on_leave', 'inactive', 'terminated'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['society_id', 'employee_code']);
            $table->index(['society_id', 'department', 'status']);
        });

        Schema::create('staff_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->timestamp('check_in_at')->nullable();
            $table->timestamp('check_out_at')->nullable();
            $table->enum('status', ['present', 'absent', 'half_day', 'leave', 'holiday', 'week_off'])->default('present');
            $table->decimal('hours_worked', 6, 2)->nullable();
            $table->decimal('overtime_hours', 6, 2)->default(0);
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['staff_id', 'attendance_date']);
            $table->index(['society_id', 'attendance_date']);
        });

        // Maids, drivers, cooks and other help engaged by individual units.
        Schema::create('daily_helps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('code', 30)->nullable();
            $table->enum('type', [
                'maid', 'cook', 'driver', 'nanny', 'caretaker',
                'newspaper', 'milk', 'laundry', 'tutor', 'other',
            ])->default('maid');
            $table->string('photo_path')->nullable();
            $table->string('id_proof_type', 40)->nullable();
            $table->string('id_proof_number', 40)->nullable();
            $table->boolean('police_verified')->default(false);
            $table->date('police_verified_on')->nullable();
            $table->string('qr_token', 64)->nullable()->unique();
            $table->enum('status', ['active', 'blacklisted', 'inactive'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['society_id', 'code']);
        });

        // Which units a given help works for.
        Schema::create('daily_help_unit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_help_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->time('expected_from')->nullable();
            $table->time('expected_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['daily_help_id', 'unit_id']);
        });

        Schema::create('daily_help_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('daily_help_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->date('attendance_date');
            $table->timestamp('entered_at')->nullable();
            $table->timestamp('exited_at')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['society_id', 'attendance_date']);
            $table->index(['daily_help_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_help_attendances');
        Schema::dropIfExists('daily_help_unit');
        Schema::dropIfExists('daily_helps');
        Schema::dropIfExists('staff_attendances');
        Schema::dropIfExists('staff');
    }
};
