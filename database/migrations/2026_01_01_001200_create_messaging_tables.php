<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The wording of every automated message, editable by the committee.
         *
         * A society writes to its residents in its own voice and often in its
         * own language, so the text lives in the database rather than in a
         * Blade file. Each row is a society's override of a packaged default;
         * deleting it falls back to the default rather than sending nothing.
         */
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60);
            $table->enum('channel', ['email', 'sms', 'whatsapp', 'in_app'])->default('email');
            $table->string('name');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['society_id', 'key', 'channel']);
        });

        /*
         * When a reminder goes out, relative to the thing it is about.
         *
         * Offsets are in days and signed: -3 is three days before a bill is
         * due, 7 is a week after. A committee that finds residents ignoring
         * five reminders can delete four of them here without a deploy.
         */
        Schema::create('reminder_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->enum('event', [
                'invoice_due', 'complaint_breach', 'meeting', 'amenity_booking', 'document_expiry',
            ])->default('invoice_due');
            $table->string('label')->nullable();
            $table->integer('offset_days');
            $table->string('template_key', 60)->nullable();
            $table->json('channels')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->unique(['society_id', 'event', 'offset_days']);
            $table->index(['society_id', 'event', 'is_active']);
        });

        /*
         * What was actually sent, to whom, and when.
         *
         * A resident who says "I never got a reminder" is the reason this
         * exists: the committee can point at the row. It also stops a rule
         * firing twice for the same bill on the same day.
         */
        Schema::create('message_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('template_key', 60);
            $table->enum('channel', ['email', 'sms', 'whatsapp', 'in_app'])->default('email');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->nullableMorphs('related');
            $table->string('dedupe_key')->nullable();
            $table->enum('status', ['queued', 'sent', 'failed'])->default('sent');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['society_id', 'dedupe_key']);
            $table->index(['society_id', 'template_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_dispatches');
        Schema::dropIfExists('reminder_rules');
        Schema::dropIfExists('message_templates');
    }
};
