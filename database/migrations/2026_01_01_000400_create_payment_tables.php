<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payer_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('payment_number', 40);
            $table->decimal('amount', 14, 2);
            // Any part not yet applied to an invoice sits here as unit credit.
            $table->decimal('unallocated_amount', 14, 2)->default(0);
            $table->timestamp('paid_at');

            $table->enum('method', [
                'cash', 'cheque', 'demand_draft', 'neft', 'rtgs', 'imps',
                'upi', 'card', 'netbanking', 'wallet', 'adjustment', 'other',
            ])->default('cash');

            // Offline payments are recorded by a committee member and need
            // approval; online ones are confirmed by the gateway webhook.
            $table->enum('mode', ['offline', 'online'])->default('offline');

            $table->enum('status', [
                'pending', 'awaiting_approval', 'completed', 'failed',
                'cancelled', 'refunded', 'bounced',
            ])->default('completed');

            $table->string('reference_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->date('instrument_date')->nullable();

            $table->foreignId('payment_gateway_id')->nullable()->constrained()->nullOnDelete();
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->string('gateway_signature')->nullable();
            $table->json('gateway_response')->nullable();
            $table->decimal('gateway_fee', 10, 2)->default(0);

            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejection_reason')->nullable();

            $table->string('attachment_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['society_id', 'payment_number']);
            $table->index(['society_id', 'status', 'paid_at']);
            $table->index(['society_id', 'unit_id']);
        });

        // Applies a payment against one or more invoices. A payment may be
        // split across bills, and an invoice may be settled by several payments.
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id']);
            $table->index(['society_id', 'invoice_id']);
        });

        // The digital receipt. Issued automatically on every completed payment,
        // numbered in its own gap-free series, and rendered to PDF on demand.
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('receipt_number', 40);
            $table->date('issued_on');
            $table->decimal('amount', 14, 2);
            $table->string('received_from')->nullable();
            $table->text('towards')->nullable();
            $table->string('pdf_path')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('emailed_at')->nullable();
            $table->boolean('is_cancelled')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();

            $table->unique(['society_id', 'receipt_number']);
        });

        // Waivers, write-offs and manual corrections to a unit's balance.
        Schema::create('adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('society_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->enum('type', ['credit_note', 'debit_note', 'waiver', 'write_off', 'opening_balance'])
                ->default('credit_note');
            $table->decimal('amount', 14, 2);
            $table->date('effective_on');
            $table->text('reason');
            $table->enum('status', ['pending_approval', 'approved', 'rejected'])->default('pending_approval');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['society_id', 'number']);
            $table->index(['society_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adjustments');
        Schema::dropIfExists('receipts');
        Schema::dropIfExists('payment_allocations');
        Schema::dropIfExists('payments');
    }
};
