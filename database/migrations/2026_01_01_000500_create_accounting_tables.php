<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Chart of accounts. Seeded with a standard society set, extensible per society.
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('ledger_accounts')->nullOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->enum('type', ['asset', 'liability', 'income', 'expense', 'equity']);
            $table->string('sub_type', 40)->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->enum('opening_balance_side', ['debit', 'credit'])->default('debit');
            // System accounts are referenced by code from the posting services
            // and must not be deleted.
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['society_id', 'code']);
            $table->index(['society_id', 'type']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('account_number', 40)->nullable();
            $table->string('ifsc', 20)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch')->nullable();
            $table->enum('type', ['savings', 'current', 'fixed_deposit', 'cash_in_hand'])->default('savings');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->date('opening_balance_as_on')->nullable();
            $table->date('maturity_date')->nullable();          // for fixed deposits
            $table->decimal('interest_rate', 6, 3)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['society_id', 'is_active']);
        });

        // Double-entry journal. Every invoice, payment and expense posts here,
        // which is what makes the financial reports trustworthy.
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_year_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_number', 40);
            $table->date('entry_date');
            $table->enum('type', ['invoice', 'payment', 'expense', 'expense_payment', 'adjustment', 'opening', 'manual', 'contra'])
                ->default('manual');
            $table->text('narration')->nullable();

            // Links the entry back to the document that produced it.
            $table->nullableMorphs('source');

            $table->decimal('total_debit', 14, 2)->default(0);
            $table->decimal('total_credit', 14, 2)->default(0);
            $table->boolean('is_posted')->default(true);
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->timestamps();

            $table->unique(['society_id', 'entry_number']);
            $table->index(['society_id', 'entry_date']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->cascadeOnDelete();
            // Optional analytical dimensions for per-unit and per-vendor reporting.
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('debit', 14, 2)->default(0);
            $table->decimal('credit', 14, 2)->default(0);
            $table->string('memo')->nullable();
            $table->timestamps();

            $table->index(['society_id', 'ledger_account_id']);
            $table->index(['journal_entry_id']);
        });

        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->string('category')->nullable(); // housekeeping, lift, security, plumbing...
            $table->string('contact_person')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('gstin', 20)->nullable();
            $table->string('pan', 12)->nullable();
            $table->string('bank_account_number', 40)->nullable();
            $table->string('bank_ifsc', 20)->nullable();
            $table->decimal('tds_rate', 5, 2)->default(0);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->date('contract_start')->nullable();
            $table->date('contract_end')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['society_id', 'code']);
            $table->index(['society_id', 'is_active']);
        });

        // Vendor bills and society spending, with an approval trail.
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('charge_head_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ledger_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('financial_year_id')->nullable()->constrained()->nullOnDelete();

            $table->string('expense_number', 40);
            $table->string('bill_number')->nullable();
            $table->date('bill_date');
            $table->date('due_date')->nullable();

            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('tds_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0);

            $table->enum('status', [
                'draft', 'pending_approval', 'approved', 'rejected',
                'partially_paid', 'paid', 'cancelled',
            ])->default('draft');

            $table->text('description')->nullable();
            $table->string('attachment_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['society_id', 'expense_number']);
            $table->index(['society_id', 'status', 'bill_date']);
        });

        Schema::create('expense_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('voucher_number', 40);
            $table->decimal('amount', 14, 2);
            $table->date('paid_on');
            $table->enum('method', ['cash', 'cheque', 'neft', 'rtgs', 'imps', 'upi', 'card', 'other'])->default('neft');
            $table->string('reference_number')->nullable();
            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['society_id', 'voucher_number']);
        });

        // Budget targets per head for a financial year, compared against actuals.
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charge_head_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ledger_account_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['income', 'expense'])->default('expense');
            $table->decimal('budgeted_amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['society_id', 'financial_year_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('expense_payments');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('vendors');
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('ledger_accounts');
    }
};
