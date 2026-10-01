<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaint_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('complaint_categories')->nullOnDelete();
            $table->string('name');
            $table->string('icon', 40)->nullable();
            $table->enum('default_priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            // Service-level target, in hours, used to compute each ticket's due time.
            $table->unsignedSmallInteger('response_sla_hours')->default(8);
            $table->unsignedSmallInteger('resolution_sla_hours')->default(48);
            $table->foreignId('default_assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['society_id', 'is_active']);
        });

        Schema::create('complaints', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_number', 40);
            $table->foreignId('complaint_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('title');
            $table->text('description');
            $table->string('location')->nullable();
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');

            $table->enum('status', [
                'open', 'assigned', 'in_progress', 'on_hold',
                'resolved', 'closed', 'reopened', 'cancelled',
            ])->default('open');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();

            // SLA clocks.
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('is_sla_breached')->default(false);
            $table->unsignedTinyInteger('escalation_level')->default(0);
            $table->timestamp('escalated_at')->nullable();

            $table->text('resolution_notes')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('feedback')->nullable();
            $table->unsignedTinyInteger('reopen_count')->default(0);

            // Visible to the whole society (e.g. a broken lift) vs. private.
            $table->boolean('is_public')->default(false);
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['society_id', 'ticket_number']);
            $table->index(['society_id', 'status', 'priority']);
            $table->index(['society_id', 'assigned_to']);
        });

        Schema::create('complaint_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            // Internal notes stay hidden from the resident who raised the ticket.
            $table->boolean('is_internal')->default(false);
            $table->json('attachments')->nullable();
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });

        Schema::create('complaint_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('complaint_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['complaint_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaint_status_logs');
        Schema::dropIfExists('complaint_comments');
        Schema::dropIfExists('complaints');
        Schema::dropIfExists('complaint_categories');
    }
};
