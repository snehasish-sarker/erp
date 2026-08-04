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
            'supplier_ledger_entries',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('supplier_id')
                    ->constrained('suppliers')
                    ->restrictOnDelete();

                $table->foreignId('accounting_period_id')
                    ->constrained('accounting_periods')
                    ->restrictOnDelete();

                $table->string('reference', 190);
                $table->string('posting_key', 190);
                $table->string('journal_reference', 190);

                $table->string('entry_type', 40)
                    ->comment(
                        'invoice, invoice_reversal, debit_note, debit_note_reversal, payment, payment_reversal, adjustment, adjustment_reversal',
                    );

                $table->string('source_type', 190);
                $table->unsignedBigInteger('source_id');

                $table->string(
                    'source_document_number',
                    160,
                )->nullable();

                $table->date('document_date');
                $table->date('posting_date');
                $table->date('due_date')->nullable();

                $table->char('currency_code', 3)
                    ->comment('ISO 4217 document currency');

                $table->decimal(
                    'exchange_rate',
                    20,
                    8,
                )->default(1);

                $table->decimal(
                    'debit_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'credit_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'base_debit_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'base_credit_amount',
                    20,
                    6,
                )->default(0);

                $table->string(
                    'description',
                    500,
                )->nullable();

                $table->foreignId('created_by_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('reversal_of_id')
                    ->nullable()
                    ->constrained('supplier_ledger_entries')
                    ->restrictOnDelete();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'reference',
                    ],
                    'supplier_ledger_tenant_reference_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'posting_key',
                    ],
                    'supplier_ledger_tenant_posting_key_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'reversal_of_id',
                    ],
                    'supplier_ledger_tenant_reversal_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_id',
                        'posting_date',
                    ],
                    'supplier_ledger_supplier_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'posting_date',
                    ],
                    'supplier_ledger_branch_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'accounting_period_id',
                        'entry_type',
                    ],
                    'supplier_ledger_period_type_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'source_type',
                        'source_id',
                    ],
                    'supplier_ledger_source_index',
                );
            },
        );

        Schema::create(
            'supplier_open_items',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('supplier_id')
                    ->constrained('suppliers')
                    ->restrictOnDelete();

                $table->foreignId('accounting_period_id')
                    ->constrained('accounting_periods')
                    ->restrictOnDelete();

                $table->foreignId('supplier_ledger_entry_id')
                    ->unique()
                    ->constrained('supplier_ledger_entries')
                    ->restrictOnDelete();

                $table->string('item_type', 30)
                    ->comment(
                        'invoice, credit, payment, adjustment',
                    );

                $table->string('source_type', 190);
                $table->unsignedBigInteger('source_id');

                $table->string(
                    'document_number',
                    160,
                )->nullable();

                $table->date('document_date');
                $table->date('posting_date');
                $table->date('due_date')->nullable();

                $table->char('currency_code', 3)
                    ->comment('ISO 4217 document currency');

                $table->decimal(
                    'exchange_rate',
                    20,
                    8,
                )->default(1);

                $table->decimal(
                    'original_amount',
                    20,
                    6,
                );

                $table->decimal(
                    'allocated_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'outstanding_amount',
                    20,
                    6,
                );

                $table->decimal(
                    'base_original_amount',
                    20,
                    6,
                );

                $table->decimal(
                    'base_allocated_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'base_outstanding_amount',
                    20,
                    6,
                );

                $table->string('status', 30)
                    ->default('open')
                    ->comment(
                        'open, partially_settled, settled, reversed',
                    );

                $table->foreignId('created_by_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'source_type',
                        'source_id',
                        'item_type',
                    ],
                    'supplier_open_items_source_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_id',
                        'status',
                        'due_date',
                    ],
                    'supplier_open_items_supplier_due_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'posting_date',
                    ],
                    'supplier_open_items_branch_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'currency_code',
                        'status',
                    ],
                    'supplier_open_items_currency_status_index',
                );
            },
        );

        Schema::create(
            'supplier_open_item_allocations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('supplier_id')
                    ->constrained('suppliers')
                    ->restrictOnDelete();

                $table->foreignId('accounting_period_id')
                    ->constrained('accounting_periods')
                    ->restrictOnDelete();

                $table->foreignId('payable_open_item_id')
                    ->constrained('supplier_open_items')
                    ->restrictOnDelete();

                $table->foreignId('credit_open_item_id')
                    ->constrained('supplier_open_items')
                    ->restrictOnDelete();

                $table->string('allocation_type', 30)
                    ->comment(
                        'debit_note, payment, manual, adjustment',
                    );

                $table->string('posting_key', 190);

                $table->string('source_type', 190)
                    ->nullable();

                $table->unsignedBigInteger('source_id')
                    ->nullable();

                $table->date('allocation_date');
                $table->date('posting_date');

                $table->char('currency_code', 3)
                    ->comment('ISO 4217 allocation currency');

                $table->decimal(
                    'amount',
                    20,
                    6,
                );

                $table->decimal(
                    'base_amount',
                    20,
                    6,
                );

                $table->string('status', 20)
                    ->default('applied')
                    ->comment('applied, reversed');

                $table->foreignId('created_by_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('reversed_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('reversed_at')->nullable();

                $table->string(
                    'reversal_reason',
                    500,
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'posting_key',
                    ],
                    'supplier_open_alloc_tenant_key_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'payable_open_item_id',
                        'status',
                    ],
                    'supplier_open_alloc_payable_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'credit_open_item_id',
                        'status',
                    ],
                    'supplier_open_alloc_credit_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'source_type',
                        'source_id',
                    ],
                    'supplier_open_alloc_source_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'supplier_open_item_allocations',
        );

        Schema::dropIfExists(
            'supplier_open_items',
        );

        Schema::dropIfExists(
            'supplier_ledger_entries',
        );
    }
};