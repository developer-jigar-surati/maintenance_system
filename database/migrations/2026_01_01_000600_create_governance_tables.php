<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('term_start');
            $table->date('term_end')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['society_id', 'is_active']);
        });

        Schema::create('committee_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('designation', [
                'president', 'vice_president', 'secretary', 'joint_secretary',
                'treasurer', 'joint_treasurer', 'member', 'advisor',
            ])->default('member');
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['committee_id', 'user_id', 'designation']);
        });

        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('reference_number', 40)->nullable();
            $table->string('title');
            $table->enum('type', ['agm', 'sgm', 'committee', 'general_body', 'emergency', 'other'])->default('committee');
            $table->text('description')->nullable();

            $table->dateTime('scheduled_at');
            $table->dateTime('ends_at')->nullable();
            $table->string('venue')->nullable();
            $table->enum('mode', ['physical', 'online', 'hybrid'])->default('physical');
            $table->string('meeting_link')->nullable();

            // Statutory notice period and quorum tracking for AGMs.
            $table->unsignedSmallInteger('notice_days')->default(14);
            $table->timestamp('notice_sent_at')->nullable();
            $table->unsignedInteger('quorum_required')->default(0);
            $table->boolean('quorum_met')->default(false);

            $table->enum('status', ['draft', 'scheduled', 'in_progress', 'completed', 'cancelled', 'adjourned'])
                ->default('draft');

            // Who may attend and vote.
            $table->enum('audience', ['all_members', 'owners_only', 'committee_only', 'custom'])->default('all_members');

            $table->longText('minutes')->nullable();
            $table->foreignId('minutes_recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('minutes_published_at')->nullable();
            $table->foreignId('minutes_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('minutes_approved_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['society_id', 'status', 'scheduled_at']);
        });

        Schema::create('meeting_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('attachment_path')->nullable();
            $table->text('discussion_notes')->nullable();
            $table->enum('outcome', ['pending', 'discussed', 'approved', 'rejected', 'deferred'])->default('pending');
            $table->timestamps();

            $table->index(['meeting_id', 'sort_order']);
        });

        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('rsvp', ['no_response', 'yes', 'no', 'maybe'])->default('no_response');
            $table->timestamp('rsvp_at')->nullable();
            $table->boolean('attended')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            // Proxy attendance, common at AGMs when an owner cannot attend.
            $table->foreignId('proxy_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('proxy_document_path')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id']);
        });

        Schema::create('meeting_resolutions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_agenda_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40)->nullable();
            $table->string('title');
            $table->text('text');
            $table->enum('type', ['ordinary', 'special'])->default('ordinary');
            $table->decimal('votes_for', 12, 2)->default(0);
            $table->decimal('votes_against', 12, 2)->default(0);
            $table->decimal('votes_abstain', 12, 2)->default(0);
            $table->enum('result', ['pending', 'passed', 'rejected', 'deferred'])->default('pending');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });

        // Follow-ups arising from a meeting, so decisions do not get lost.
        Schema::create('action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meeting_agenda_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'completed', 'cancelled'])->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->text('completion_notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['society_id', 'status', 'due_date']);
        });

        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('meeting_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('meeting_resolution_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['single_choice', 'multiple_choice', 'yes_no', 'rating'])->default('single_choice');

            // One vote per person, per unit, or weighted by the unit's area - // the last of which matches how many bye-laws apportion voting rights.
            $table->enum('voting_basis', ['per_user', 'per_unit', 'weighted_by_area'])->default('per_unit');

            $table->enum('eligibility', ['all_members', 'owners_only', 'committee_only'])->default('all_members');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('show_results_before_close')->default(false);
            $table->enum('status', ['draft', 'open', 'closed', 'cancelled'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['society_id', 'status']);
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_id')->constrained()->cascadeOnDelete();
            $table->foreignId('poll_option_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            // Carries the unit's area for weighted polls, 1 otherwise.
            $table->decimal('weight', 12, 4)->default(1);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('voted_at');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['poll_id', 'poll_option_id']);
            $table->index(['society_id', 'poll_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
        Schema::dropIfExists('action_items');
        Schema::dropIfExists('meeting_resolutions');
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meeting_agenda_items');
        Schema::dropIfExists('meetings');
        Schema::dropIfExists('committee_members');
        Schema::dropIfExists('committees');
    }
};
