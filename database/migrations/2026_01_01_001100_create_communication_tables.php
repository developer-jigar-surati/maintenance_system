<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('body');
            $table->enum('category', [
                'general', 'urgent', 'maintenance', 'event', 'financial',
                'meeting', 'security', 'regulatory', 'celebration',
            ])->default('general');
            $table->enum('priority', ['normal', 'important', 'critical'])->default('normal');

            // Who sees it. 'custom' reads the audience_meta payload.
            $table->enum('audience', [
                'all', 'owners', 'tenants', 'committee', 'staff', 'specific_blocks', 'specific_units',
            ])->default('all');
            $table->json('audience_meta')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('allow_comments')->default(false);
            $table->json('attachments')->nullable();
            $table->boolean('send_email')->default(false);
            $table->timestamp('email_sent_at')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['society_id', 'status', 'published_at']);
        });

        // Read receipts, so a committee can prove a circular actually landed.
        Schema::create('notice_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');
            $table->timestamps();

            $table->unique(['notice_id', 'user_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('folder_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->boolean('is_folder')->default(false);
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('category', [
                'bye_laws', 'registration', 'audit_report', 'financial_statement',
                'agm_minutes', 'circular', 'legal', 'insurance', 'floor_plan',
                'noc', 'agreement', 'tender', 'photo', 'other',
            ])->default('other');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedInteger('version')->default(1);

            $table->enum('visibility', ['all', 'owners', 'committee', 'admin_only', 'specific_units'])->default('all');
            $table->json('visibility_meta')->nullable();
            $table->date('expires_on')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['society_id', 'category']);
        });

        // Immutable trail of who changed what. Written by an observer.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('description')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['society_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('notice_reads');
        Schema::dropIfExists('notices');
    }
};
