<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'treasury_transfers',
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
                $table->foreignId('source_branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('destination_branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('source_account_id')->constrained('accounts')->restrictOnDelete();
                $table->foreignId('destination_account_id')->constrained('accounts')->restrictOnDelete();
                $table->foreignId('document_number_allocation_id')
                    ->nullable()
                    ->unique()
                    ->constrained('document_number_allocations')
                    ->restrictOnDelete();
                $table->string('transfer_number', 160)->nullable();
                $table->date('transfer_date');
                $table->date('posting_date');
                $table->char('currency_code', 3);
                $table->decimal('exchange_rate', 20, 8)->default(1);
                $table->decimal('amount', 20, 6);
                $table->decimal('base_amount', 20, 6)->default(0);
                $table->string('transfer_type', 40)
                    ->comment('cash_to_cash, cash_to_bank, bank_to_cash, bank_to_bank');
                $table->string('reference', 160)->nullable();
                $table->string('source_account_code', 50);
                $table->string('source_account_name', 160);
                $table->string('source_control_type', 20);
                $table->string('destination_account_code', 50);
                $table->string('destination_account_name', 160);
                $table->string('destination_control_type', 20);
                $table->string('status', 30)
                    ->default('draft')
                    ->comment('draft, submitted, approved, posted, reversed, cancelled');
                $table->text('notes')->nullable();
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->string('accounting_posting_reference', 190)->nullable();
                $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->date('reversal_posting_date')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->string('reversal_reason', 500)->nullable();
                $table->string('accounting_reversal_reference', 190)->nullable();
                $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancellation_reason', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['tenant_id', 'transfer_number'],
                    'treasury_transfers_tenant_number_unique',
                );
                $table->index(
                    ['tenant_id', 'source_branch_id', 'status', 'posting_date'],
                    'treasury_transfers_source_status_date_index',
                );
                $table->index(
                    ['tenant_id', 'destination_branch_id', 'status', 'posting_date'],
                    'treasury_transfers_destination_status_date_index',
                );
            },
        );

        Schema::create(
            'bank_statement_imports',
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('bank_account_id')->constrained('accounts')->restrictOnDelete();
                $table->string('statement_reference', 160)->nullable();
                $table->string('source_filename', 255);
                $table->char('source_sha256', 64);
                $table->date('period_start');
                $table->date('period_end');
                $table->char('currency_code', 3);
                $table->decimal('opening_balance', 20, 6);
                $table->decimal('closing_balance', 20, 6);
                $table->unsignedInteger('line_count')->default(0);
                $table->string('status', 30)
                    ->default('imported')
                    ->comment('imported, reconciled, reversed');
                $table->foreignId('imported_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('imported_at');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['tenant_id', 'bank_account_id', 'source_sha256'],
                    'bank_statement_imports_account_hash_unique',
                );
                $table->index(
                    ['tenant_id', 'branch_id', 'bank_account_id', 'period_end'],
                    'bank_statement_imports_account_period_index',
                );
            },
        );

        Schema::create(
            'bank_statement_lines',
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
                $table->foreignId('bank_statement_import_id')
                    ->constrained('bank_statement_imports')
                    ->cascadeOnDelete();
                $table->foreignId('bank_account_id')->constrained('accounts')->restrictOnDelete();
                $table->unsignedInteger('line_number');
                $table->date('transaction_date');
                $table->date('value_date')->nullable();
                $table->string('bank_reference', 190)->nullable();
                $table->string('description', 500);
                $table->decimal('debit_amount', 20, 6)->default(0);
                $table->decimal('credit_amount', 20, 6)->default(0);
                $table->decimal('signed_amount', 20, 6);
                $table->decimal('running_balance', 20, 6)->nullable();
                $table->decimal('matched_amount', 20, 6)->default(0);
                $table->string('fingerprint', 64);
                $table->string('status', 30)
                    ->default('unmatched')
                    ->comment('unmatched, partially_matched, matched, ignored');
                $table->string('ignore_reason', 500)->nullable();
                $table->foreignId('ignored_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('ignored_at')->nullable();
                $table->timestamps();

                $table->unique(
                    ['bank_statement_import_id', 'line_number'],
                    'bank_statement_lines_import_line_unique',
                );
                $table->unique(
                    ['bank_statement_import_id', 'fingerprint'],
                    'bank_statement_lines_import_fingerprint_unique',
                );
                $table->index(
                    ['tenant_id', 'bank_account_id', 'transaction_date', 'status'],
                    'bank_statement_lines_account_date_status_index',
                );
            },
        );

        Schema::create(
            'bank_reconciliations',
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('bank_account_id')->constrained('accounts')->restrictOnDelete();
                $table->foreignId('bank_statement_import_id')
                    ->constrained('bank_statement_imports')
                    ->restrictOnDelete();
                $table->foreignId('document_number_allocation_id')
                    ->nullable()
                    ->unique()
                    ->constrained('document_number_allocations')
                    ->restrictOnDelete();
                $table->string('reconciliation_number', 160)->nullable();
                $table->string('active_key', 190)->nullable()->unique();
                $table->date('statement_start_date');
                $table->date('statement_end_date');
                $table->char('currency_code', 3);
                $table->decimal('statement_opening_balance', 20, 6);
                $table->decimal('statement_closing_balance', 20, 6);
                $table->decimal('book_closing_balance', 20, 6)->default(0);
                $table->decimal('outstanding_deposits', 20, 6)->default(0);
                $table->decimal('outstanding_payments', 20, 6)->default(0);
                $table->decimal('adjusted_bank_balance', 20, 6)->default(0);
                $table->decimal('difference_amount', 20, 6)->default(0);
                $table->string('status', 30)
                    ->default('draft')
                    ->comment('draft, completed, reversed');
                $table->text('notes')->nullable();
                $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('completed_at')->nullable();
                $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('reversed_at')->nullable();
                $table->string('reversal_reason', 500)->nullable();
                $table->timestamps();

                $table->unique(
                    ['tenant_id', 'reconciliation_number'],
                    'bank_reconciliations_tenant_number_unique',
                );
                $table->index(
                    ['tenant_id', 'branch_id', 'bank_account_id', 'statement_end_date', 'status'],
                    'bank_reconciliations_account_date_status_index',
                );
            },
        );

        Schema::create(
            'bank_reconciliation_matches',
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
                $table->foreignId('bank_reconciliation_id')
                    ->constrained('bank_reconciliations')
                    ->cascadeOnDelete();
                $table->foreignId('bank_statement_line_id')
                    ->constrained('bank_statement_lines')
                    ->restrictOnDelete();
                $table->foreignId('journal_entry_line_id')
                    ->constrained('journal_entry_lines')
                    ->restrictOnDelete();
                $table->string('match_type', 30)
                    ->comment('automatic, manual, adjustment');
                $table->decimal('matched_amount', 20, 6);
                $table->string('active_key', 190)->nullable()->unique();
                $table->string('status', 20)
                    ->default('active')
                    ->comment('active, reversed');
                $table->foreignId('matched_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamp('matched_at');
                $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();

                $table->index(
                    ['tenant_id', 'bank_statement_line_id', 'status'],
                    'bank_reconciliation_matches_statement_status_index',
                );
                $table->index(
                    ['tenant_id', 'journal_entry_line_id', 'status'],
                    'bank_reconciliation_matches_journal_status_index',
                );
            },
        );

        Schema::create(
            'treasury_adjustments',
            function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
                $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
                $table->foreignId('bank_account_id')->constrained('accounts')->restrictOnDelete();
                $table->foreignId('offset_account_id')->constrained('accounts')->restrictOnDelete();
                $table->foreignId('bank_statement_line_id')
                    ->nullable()
                    ->constrained('bank_statement_lines')
                    ->restrictOnDelete();
                $table->string('active_statement_key', 190)->nullable()->unique();
                $table->foreignId('document_number_allocation_id')
                    ->nullable()
                    ->unique()
                    ->constrained('document_number_allocations')
                    ->restrictOnDelete();
                $table->string('adjustment_number', 160)->nullable();
                $table->string('adjustment_type', 40)
                    ->comment('bank_charge, bank_interest, other_debit, other_credit');
                $table->date('adjustment_date');
                $table->date('posting_date');
                $table->char('currency_code', 3);
                $table->decimal('exchange_rate', 20, 8)->default(1);
                $table->decimal('amount', 20, 6);
                $table->decimal('base_amount', 20, 6)->default(0);
                $table->string('reference', 160)->nullable();
                $table->string('bank_account_code', 50);
                $table->string('bank_account_name', 160);
                $table->string('offset_account_code', 50);
                $table->string('offset_account_name', 160);
                $table->string('description', 500);
                $table->string('status', 30)
                    ->default('draft')
                    ->comment('draft, submitted, approved, posted, reversed, cancelled');
                $table->unsignedInteger('revision')->default(1);
                $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->foreignId('posted_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->string('accounting_posting_reference', 190)->nullable();
                $table->foreignId('reversed_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->date('reversal_posting_date')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->string('reversal_reason', 500)->nullable();
                $table->string('accounting_reversal_reference', 190)->nullable();
                $table->foreignId('cancelled_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancellation_reason', 500)->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['tenant_id', 'adjustment_number'],
                    'treasury_adjustments_tenant_number_unique',
                );
                $table->index(
                    ['tenant_id', 'branch_id', 'bank_account_id', 'posting_date', 'status'],
                    'treasury_adjustments_account_date_status_index',
                );
                $table->index(
                    ['tenant_id', 'bank_statement_line_id', 'status'],
                    'treasury_adjustments_statement_status_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('treasury_adjustments');
        Schema::dropIfExists('bank_reconciliation_matches');
        Schema::dropIfExists('bank_reconciliations');
        Schema::dropIfExists('bank_statement_lines');
        Schema::dropIfExists('bank_statement_imports');
        Schema::dropIfExists('treasury_transfers');
    }
};