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
            'accounts',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('parent_account_id')
                    ->nullable()
                    ->constrained('accounts')
                    ->restrictOnDelete();

                $table->string('code', 50);
                $table->string('name', 160);

                $table->string('account_type', 30)
                    ->comment(
                        'asset, liability, equity, revenue, expense',
                    );

                $table->string('account_subtype', 50)
                    ->nullable();

                $table->string('normal_balance', 10)
                    ->comment('debit, credit');

                $table->string('control_type', 40)
                    ->nullable()
                    ->comment(
                        'accounts_payable, accounts_receivable, inventory, tax, cash, bank',
                    );

                $table->string('system_key', 80)
                    ->nullable()
                    ->comment(
                        'Stable application-level account purpose such as accounts_payable_control',
                    );

                $table->unsignedTinyInteger('level')
                    ->default(1);

                $table->boolean('is_group')
                    ->default(false);

                $table->boolean('allow_manual_posting')
                    ->default(true);

                $table->string('status', 20)
                    ->default('active')
                    ->comment('active, inactive');

                $table->string('description', 500)
                    ->nullable();

                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('updated_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'code',
                    ],
                    'accounts_tenant_code_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'system_key',
                    ],
                    'accounts_tenant_system_key_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'parent_account_id',
                        'status',
                    ],
                    'accounts_parent_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'account_type',
                        'status',
                    ],
                    'accounts_type_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'control_type',
                        'status',
                    ],
                    'accounts_control_status_index',
                );
            },
        );

        Schema::create(
            'journal_entries',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('accounting_period_id')
                    ->constrained('accounting_periods')
                    ->restrictOnDelete();

                $table->foreignId('document_number_allocation_id')
                    ->nullable()
                    ->unique()
                    ->constrained('document_number_allocations')
                    ->restrictOnDelete();

                $table->string('journal_number', 160)
                    ->nullable();

                $table->string('posting_key', 190)
                    ->nullable();

                $table->string('journal_type', 50)
                    ->comment(
                        'manual, supplier_invoice, supplier_invoice_reversal, supplier_debit_note, supplier_debit_note_reversal, supplier_payment, supplier_payment_reversal, inventory, inventory_reversal, opening_balance, closing, adjustment, adjustment_reversal',
                    );

                $table->string('status', 20)
                    ->default('draft')
                    ->comment(
                        'draft, approved, posted, reversed, cancelled',
                    );

                $table->nullableMorphs('source');

                $table->string('source_document_number', 160)
                    ->nullable();

                $table->date('document_date');
                $table->date('posting_date');

                $table->char('currency_code', 3)
                    ->comment('ISO 4217 transaction currency');

                $table->decimal('exchange_rate', 20, 8)
                    ->default(1);

                $table->decimal('total_debit', 20, 6)
                    ->default(0);

                $table->decimal('total_credit', 20, 6)
                    ->default(0);

                $table->decimal('base_total_debit', 20, 6)
                    ->default(0);

                $table->decimal('base_total_credit', 20, 6)
                    ->default(0);

                $table->string('description', 500);

                $table->foreignId('prepared_by_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('approved_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('approved_at')
                    ->nullable();

                $table->foreignId('posted_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('posted_at')
                    ->nullable();

                $table->foreignId('reversal_of_id')
                    ->nullable()
                    ->constrained('journal_entries')
                    ->restrictOnDelete();

                $table->string('reversal_reason', 500)
                    ->nullable();

                $table->foreignId('cancelled_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('cancelled_at')
                    ->nullable();

                $table->string('cancellation_reason', 500)
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'journal_number',
                    ],
                    'journal_entries_tenant_number_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'posting_key',
                    ],
                    'journal_entries_tenant_posting_key_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'reversal_of_id',
                    ],
                    'journal_entries_tenant_reversal_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'posting_date',
                        'status',
                    ],
                    'journal_entries_branch_date_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'accounting_period_id',
                        'status',
                    ],
                    'journal_entries_period_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'journal_type',
                        'status',
                    ],
                    'journal_entries_type_status_index',
                );
            },
        );

        Schema::create(
            'journal_entry_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('journal_entry_id')
                    ->constrained('journal_entries')
                    ->cascadeOnDelete();

                $table->unsignedSmallInteger('line_number');

                $table->foreignId('account_id')
                    ->constrained('accounts')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('supplier_id')
                    ->nullable()
                    ->constrained('suppliers')
                    ->restrictOnDelete();

                $table->foreignId('customer_id')
                    ->nullable()
                    ->constrained('customers')
                    ->restrictOnDelete();

                $table->string('reference', 160)
                    ->nullable();

                $table->string('description', 500);

                $table->date('due_date')
                    ->nullable();

                $table->char('currency_code', 3)
                    ->comment('ISO 4217 line currency');

                $table->decimal('exchange_rate', 20, 8)
                    ->default(1);

                $table->decimal('debit_amount', 20, 6)
                    ->default(0);

                $table->decimal('credit_amount', 20, 6)
                    ->default(0);

                $table->decimal('base_debit_amount', 20, 6)
                    ->default(0);

                $table->decimal('base_credit_amount', 20, 6)
                    ->default(0);

                $table->timestamps();

                $table->unique(
                    [
                        'journal_entry_id',
                        'line_number',
                    ],
                    'journal_entry_lines_entry_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'account_id',
                        'journal_entry_id',
                    ],
                    'journal_entry_lines_account_entry_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'account_id',
                    ],
                    'journal_entry_lines_branch_account_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_id',
                        'journal_entry_id',
                    ],
                    'journal_entry_lines_supplier_entry_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_id',
                        'journal_entry_id',
                    ],
                    'journal_entry_lines_customer_entry_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('accounts');
    }
};