<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The tenant. Every other table in the system hangs off this one and is
        // filtered by it through the BelongsToSociety global scope.
        Schema::create('societies', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('code', 20)->unique();

            // Deliberately broad: this platform is not apartment-only.
            $table->enum('type', [
                'apartment', 'villa', 'row_house', 'bungalow', 'gated_community',
                'township', 'plotted_development', 'builder_floor', 'commercial_complex',
                'office_park', 'industrial_estate', 'mixed_use', 'cooperative_housing',
                'student_housing', 'co_living', 'other',
            ])->default('apartment');

            $table->string('registration_number')->nullable();
            $table->date('registered_on')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->string('pan', 12)->nullable();

            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 64)->default('India');
            $table->string('postal_code', 12)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('logo_path')->nullable();

            $table->string('currency', 3)->default('INR');
            $table->string('timezone', 64)->default('Asia/Kolkata');
            $table->string('locale', 10)->default('en');
            $table->enum('area_unit', ['sqft', 'sqm', 'sqyd'])->default('sqft');

            // Indian societies commonly run April-March; keep it configurable.
            $table->unsignedTinyInteger('financial_year_start_month')->default(4);

            // How this society collects money. Set during onboarding and
            // switched to 'online'/'both' once a gateway is configured.
            $table->enum('payment_mode', ['offline', 'online', 'both'])->default('offline');

            $table->boolean('gst_enabled')->default(false);
            $table->unsignedInteger('planned_unit_count')->nullable();

            $table->json('settings')->nullable();
            $table->enum('status', ['active', 'onboarding', 'suspended', 'archived'])->default('onboarding');
            $table->timestamp('onboarded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'type']);
        });

        // Membership of a user in a society. Roles themselves live in
        // spatie/laravel-permission, scoped by the same society_id.
        Schema::create('society_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['active', 'invited', 'suspended', 'left'])->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->string('invitation_token', 64)->nullable()->unique();
            $table->timestamp('invitation_sent_at')->nullable();
            $table->timestamp('invitation_accepted_at')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['society_id', 'user_id']);
        });

        // Accounting periods. Postings are blocked once a year is closed.
        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name', 32);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->boolean('is_closed')->default(false);
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['society_id', 'name']);
            $table->index(['society_id', 'is_current']);
        });

        // Per-society document numbering (invoices, receipts, tickets...).
        // Centralised so every series is gap-free and independently resettable.
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('prefix', 20)->default('');
            $table->string('suffix', 20)->default('');
            $table->unsignedInteger('next_number')->default(1);
            $table->unsignedTinyInteger('padding')->default(4);
            $table->enum('reset_frequency', ['never', 'yearly', 'monthly'])->default('yearly');
            $table->string('period_key', 12)->nullable();
            $table->timestamps();

            $table->unique(['society_id', 'key']);
        });

        // Gateway credentials per society. Presence of an active row is what
        // enables online collection; without one the society stays offline.
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->enum('provider', ['razorpay', 'cashfree', 'stripe', 'payu'])->default('razorpay');
            $table->string('label')->nullable();
            $table->enum('environment', ['test', 'live'])->default('test');
            $table->text('credentials')->nullable();     // encrypted cast
            $table->text('webhook_secret')->nullable();  // encrypted cast
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('pass_fee_to_payer')->default(false);
            $table->decimal('convenience_fee_percent', 5, 2)->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['society_id', 'provider', 'environment']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
        Schema::dropIfExists('number_sequences');
        Schema::dropIfExists('financial_years');
        Schema::dropIfExists('society_user');
        Schema::dropIfExists('societies');
    }
};
