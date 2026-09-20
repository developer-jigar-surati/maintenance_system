<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A billable or spendable head of account: maintenance, sinking fund,
        // water, parking, festival fund, penalty, and so on.
        Schema::create('charge_heads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30);
            $table->enum('type', ['income', 'expense'])->default('income');

            // How the amount for a unit is derived at bill-generation time.
            $table->enum('basis', [
                'fixed_per_unit',   // same amount for every unit
                'per_sqft',         // rate x unit area
                'per_bedroom',      // rate x bedroom count
                'per_member',       // rate x resident count
                'per_vehicle',      // rate x vehicle count
                'manual',           // entered per unit, never auto-computed
            ])->default('fixed_per_unit');

            $table->decimal('default_rate', 12, 4)->default(0);
            $table->enum('area_basis', ['carpet_area', 'built_up_area', 'super_built_up_area'])
                ->default('carpet_area');

            // Sinking/corpus funds are tracked apart from running maintenance.
            $table->enum('fund', ['general', 'sinking', 'corpus', 'repair', 'festival', 'welfare'])->default('general');

            $table->boolean('is_taxable')->default(false);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->string('hsn_sac', 12)->nullable();

            // Restricts the head to a slice of the society.
            $table->enum('applies_to', ['all', 'owners', 'tenants', 'occupied', 'specific_unit_types'])->default('all');
            $table->json('applies_to_unit_types')->nullable();

            $table->boolean('is_recurring')->default(true);
            $table->boolean('is_system')->default(false); // e.g. the late-fee head
            $table->foreignId('ledger_account_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['society_id', 'code']);
            $table->index(['society_id', 'type', 'is_active']);
        });

        // How often bills go out, and which heads they carry.
        Schema::create('billing_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('cycle', ['monthly', 'bi_monthly', 'quarterly', 'half_yearly', 'yearly', 'one_time'])
                ->default('monthly');

            // Day of the period on which the run fires, and how long until due.
            $table->unsignedTinyInteger('generate_on_day')->default(1);
            $table->unsignedSmallInteger('due_after_days')->default(15);

            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_run_on')->nullable();
            $table->timestamp('last_run_at')->nullable();

            // When false an admin must trigger each run by hand.
            $table->boolean('auto_generate')->default(true);
            // Whether generated invoices go out as drafts for review first.
            $table->boolean('auto_issue')->default(true);

            $table->foreignId('late_fee_rule_id')->nullable();
            $table->text('invoice_notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['society_id', 'is_active']);
        });

        // Heads carried by a plan, with an optional rate override.
        Schema::create('billing_plan_charge_head', function (Blueprint $table) {
            $table->id();
            $table->foreignId('billing_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_head_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 12, 4)->nullable();
            $table->string('basis', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['billing_plan_id', 'charge_head_id']);
        });

        // Per-unit exceptions, e.g. a ground-floor unit exempt from lift charges.
        Schema::create('unit_charge_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_head_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 12, 4)->nullable();
            $table->boolean('is_exempt')->default(false);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['unit_id', 'charge_head_id']);
        });

        // Interest / penalty policy for overdue invoices.
        Schema::create('late_fee_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('method', [
                'flat',                  // fixed amount per overdue period
                'percent_per_month',     // % of outstanding, per month
                'percent_per_annum',     // % of outstanding, annualised
                'slab',                  // tiered by days overdue
            ])->default('percent_per_annum');

            $table->decimal('rate', 8, 4)->default(0);
            $table->unsignedSmallInteger('grace_days')->default(0);
            $table->enum('compounding', ['simple', 'compound'])->default('simple');

            // Interest accrues on this cadence; each run is idempotent per period.
            $table->enum('accrual_frequency', ['daily', 'monthly'])->default('monthly');

            $table->decimal('minimum_amount', 10, 2)->default(0);
            $table->decimal('maximum_amount', 10, 2)->nullable();
            // Bills below this are never penalised.
            $table->decimal('applies_above_amount', 10, 2)->default(0);
            $table->json('slabs')->nullable();

            $table->foreignId('charge_head_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('billing_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_year_id')->nullable()->constrained()->nullOnDelete();

            $table->string('invoice_number', 40);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->date('issue_date');
            $table->date('due_date');

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('late_fee_total', 14, 2)->default(0);
            // Unpaid balance rolled forward from earlier bills, shown for context.
            $table->decimal('arrears_amount', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);

            $table->enum('status', [
                'draft', 'issued', 'partially_paid', 'paid', 'overdue', 'cancelled', 'written_off',
            ])->default('draft');

            $table->timestamp('last_accrued_at')->nullable();
            $table->string('accrual_period_key', 12)->nullable();

            $table->text('notes')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['society_id', 'invoice_number']);
            $table->index(['society_id', 'status', 'due_date']);
            $table->index(['society_id', 'unit_id', 'status']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_head_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->string('basis', 30)->nullable();
            $table->decimal('quantity', 12, 4)->default(1);
            $table->decimal('rate', 12, 4)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);

            // Distinguishes generated charges from interest postings and manual adds.
            $table->enum('source', ['charge', 'late_fee', 'arrears', 'manual', 'adjustment', 'booking'])
                ->default('charge');
            $table->string('period_key', 12)->nullable();

            // Set only on interest/late-fee postings, as "<source>:<period>".
            // Null everywhere else, so the unique index below constrains
            // accruals alone and leaves ordinary charge lines unrestricted.
            $table->string('accrual_key', 40)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'source']);
            // Guards against an interest run double-posting for the same period.
            $table->unique(['invoice_id', 'accrual_key'], 'invoice_lines_accrual_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('late_fee_rules');
        Schema::dropIfExists('unit_charge_overrides');
        Schema::dropIfExists('billing_plan_charge_head');
        Schema::dropIfExists('billing_plans');
        Schema::dropIfExists('charge_heads');
    }
};
