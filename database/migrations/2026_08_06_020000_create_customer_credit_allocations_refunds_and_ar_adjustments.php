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
            'customer_credit_applications',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('customer_id')
                    ->constrained('customers')
                    ->restrictOnDelete();

                $table->foreignId(
                    'document_number_allocation_id',
                )->nullable();

                $table->unique(
                    'document_number_allocation_id',
                    'cust_credit_app_doc_alloc_uq',
                );

                $table->foreign(
                    'document_number_allocation_id',
                    'cust_credit_app_doc_alloc_fk',
                )
                    ->references('id')
                    ->on('document_number_allocations')
                    ->restrictOnDelete();

                $table->string('application_number', 160)
                    ->nullable();

                $table->date('application_date');
                $table->date('posting_date');
                $table->char('currency_code', 3);
                $table->string('customer_name', 160);
                $table->string('customer_code', 60);

                $table->string('status', 30)
                    ->default('draft')
                    ->comment(
                        'draft, submitted, approved, posted, reversed, cancelled',
                    );

                $table->decimal('total_amount', 20, 6)
                    ->default(0);

                $table->decimal('receivable_base_amount', 20, 6)
                    ->default(0);

                $table->decimal('credit_base_amount', 20, 6)
                    ->default(0);

                $table->decimal('exchange_difference_amount', 20, 6)
                    ->default(0);

                $table->string('reason', 500);
                $table->text('notes')->nullable();
                $table->unsignedInteger('revision')->default(1);

                $table->foreignId('created_by_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('submitted_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('submitted_at')->nullable();

                $table->foreignId('approved_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('approved_at')->nullable();

                $table->foreignId('posted_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('posted_at')->nullable();

                $table->string('accounting_posting_reference', 190)
                    ->nullable();

                $table->date('reversal_posting_date')->nullable();

                $table->foreignId('reversed_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('reversed_at')->nullable();
                $table->string('reversal_reason', 500)->nullable();

                $table->string('accounting_reversal_reference', 190)
                    ->nullable();

                $table->foreignId('cancelled_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancellation_reason', 500)->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['tenant_id', 'application_number'],
                    'customer_credit_applications_tenant_number_unique',
                );

                $table->index(
                    ['tenant_id', 'branch_id', 'status', 'posting_date'],
                    'customer_credit_applications_branch_status_date_index',
                );

                $table->index(
                    ['tenant_id', 'customer_id', 'status'],
                    'customer_credit_applications_customer_status_index',
                );
            },
        );

        Schema::create(
            'customer_credit_application_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId(
                    'customer_credit_application_id',
                );

                $table->foreign(
                    'customer_credit_application_id',
                    'cust_credit_app_line_parent_fk',
                )
                    ->references('id')
                    ->on('customer_credit_applications')
                    ->cascadeOnDelete();

                $table->foreignId(
                    'receivable_open_item_id',
                );

                $table->foreign(
                    'receivable_open_item_id',
                    'cust_credit_app_receivable_fk',
                )
                    ->references('id')
                    ->on('customer_open_items')
                    ->restrictOnDelete();

                $table->foreignId('credit_open_item_id')
                    ->constrained('customer_open_items')
                    ->restrictOnDelete();

                $table->foreignId(
                    'customer_open_item_allocation_id',
                )->nullable();

                $table->unique(
                    'customer_open_item_allocation_id',
                    'cust_credit_app_open_alloc_uq',
                );

                $table->foreign(
                    'customer_open_item_allocation_id',
                    'cust_credit_app_open_alloc_fk',
                )
                    ->references('id')
                    ->on('customer_open_item_allocations')
                    ->restrictOnDelete();

                $table->unsignedInteger('line_number');

                $table->string('receivable_document_number', 160)
                    ->nullable();

                $table->string('credit_document_number', 160)
                    ->nullable();

                $table->string('credit_item_type', 30);

                $table->decimal(
                    'amount',
                    20,
                    6,
                );

                $table->decimal(
                    'receivable_exchange_rate',
                    20,
                    8,
                );

                $table->decimal(
                    'credit_exchange_rate',
                    20,
                    8,
                );

                $table->decimal(
                    'receivable_base_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'credit_base_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'exchange_difference_amount',
                    20,
                    6,
                )->default(0);

                $table->string(
                    'status',
                    20,
                )
                    ->default('draft')
                    ->comment(
                        'draft, applied, reversed, cancelled',
                    );

                $table->timestamp(
                    'applied_at',
                )->nullable();

                $table->timestamp(
                    'reversed_at',
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'customer_credit_application_id',
                        'line_number',
                    ],
                    'customer_credit_application_lines_line_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'receivable_open_item_id',
                        'status',
                    ],
                    'customer_credit_application_receivable_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'credit_open_item_id',
                        'status',
                    ],
                    'customer_credit_application_credit_index',
                );
            },
        );

        Schema::create(
            'customer_refunds',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('customer_id')
                    ->constrained('customers')
                    ->restrictOnDelete();

                $table->foreignId('refund_account_id')
                    ->constrained('accounts')
                    ->restrictOnDelete();

                $table->foreignId(
                    'document_number_allocation_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained(
                        'document_number_allocations',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'customer_ledger_entry_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained(
                        'customer_ledger_entries',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'customer_open_item_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained(
                        'customer_open_items',
                    )
                    ->restrictOnDelete();

                $table->string(
                    'refund_number',
                    160,
                )->nullable();

                $table->date('refund_date');
                $table->date('posting_date');

                $table->char(
                    'currency_code',
                    3,
                );

                $table->decimal(
                    'exchange_rate',
                    20,
                    8,
                )->default(1);

                $table->string(
                    'refund_method',
                    40,
                )->comment(
                    'cash, bank_transfer, cheque, mobile_financial_service, other',
                );

                $table->string(
                    'refund_reference',
                    160,
                )->nullable();

                $table->string(
                    'cheque_number',
                    100,
                )->nullable();

                $table->date(
                    'cheque_date',
                )->nullable();

                $table->string(
                    'customer_name',
                    160,
                );

                $table->string(
                    'customer_code',
                    60,
                );

                $table->string(
                    'refund_account_code',
                    50,
                );

                $table->string(
                    'refund_account_name',
                    160,
                );

                $table->string(
                    'status',
                    30,
                )
                    ->default('draft')
                    ->comment(
                        'draft, submitted, approved, posted, reversed, cancelled',
                    );

                $table->decimal(
                    'total_amount',
                    20,
                    6,
                );

                $table->decimal(
                    'base_cash_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'base_credit_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'exchange_difference_amount',
                    20,
                    6,
                )->default(0);

                $table->string(
                    'reason',
                    500,
                );

                $table->text(
                    'notes',
                )->nullable();

                $table->unsignedInteger(
                    'revision',
                )->default(1);

                $table->foreignId(
                    'created_by_user_id',
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId(
                    'submitted_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp(
                    'submitted_at',
                )->nullable();

                $table->foreignId(
                    'approved_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp(
                    'approved_at',
                )->nullable();

                $table->foreignId(
                    'posted_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp(
                    'posted_at',
                )->nullable();

                $table->string(
                    'accounting_posting_reference',
                    190,
                )->nullable();

                $table->date(
                    'reversal_posting_date',
                )->nullable();

                $table->foreignId(
                    'reversed_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp(
                    'reversed_at',
                )->nullable();

                $table->string(
                    'reversal_reason',
                    500,
                )->nullable();

                $table->string(
                    'accounting_reversal_reference',
                    190,
                )->nullable();

                $table->foreignId(
                    'cancelled_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp(
                    'cancelled_at',
                )->nullable();

                $table->string(
                    'cancellation_reason',
                    500,
                )->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    [
                        'tenant_id',
                        'refund_number',
                    ],
                    'customer_refunds_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'posting_date',
                    ],
                    'customer_refunds_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_id',
                        'status',
                    ],
                    'customer_refunds_customer_status_index',
                );
            },
        );

        Schema::create(
            'customer_refund_allocations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId(
                    'customer_refund_id',
                )
                    ->constrained(
                        'customer_refunds',
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'credit_open_item_id',
                )
                    ->constrained(
                        'customer_open_items',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'customer_open_item_allocation_id',
                )->nullable();

                $table->unique(
                    'customer_open_item_allocation_id',
                    'cust_refund_open_alloc_uq',
                );

                $table->foreign(
                    'customer_open_item_allocation_id',
                    'cust_refund_open_alloc_fk',
                )
                    ->references('id')
                    ->on(
                        'customer_open_item_allocations',
                    )
                    ->restrictOnDelete();

                $table->unsignedInteger(
                    'line_number',
                );

                $table->string(
                    'credit_document_number',
                    160,
                )->nullable();

                $table->string(
                    'credit_item_type',
                    30,
                );

                $table->string(
                    'credit_source_type',
                    190,
                )->nullable();

                $table->unsignedBigInteger(
                    'credit_source_id',
                )->nullable();

                $table->decimal(
                    'amount',
                    20,
                    6,
                );

                $table->decimal(
                    'credit_exchange_rate',
                    20,
                    8,
                );

                $table->decimal(
                    'credit_base_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'cash_base_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'exchange_difference_amount',
                    20,
                    6,
                )->default(0);

                $table->string(
                    'status',
                    20,
                )
                    ->default('draft')
                    ->comment(
                        'draft, applied, reversed, cancelled',
                    );

                $table->timestamp(
                    'applied_at',
                )->nullable();

                $table->timestamp(
                    'reversed_at',
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'customer_refund_id',
                        'line_number',
                    ],
                    'customer_refund_allocations_line_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'credit_open_item_id',
                        'status',
                    ],
                    'customer_refund_allocations_credit_index',
                );
            },
        );

        Schema::create(
            'customer_ar_adjustments',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('customer_id')
                    ->constrained('customers')
                    ->restrictOnDelete();

                $table->foreignId('offset_account_id')
                    ->constrained('accounts')
                    ->restrictOnDelete();

                $table->foreignId(
                    'document_number_allocation_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained(
                        'document_number_allocations',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'customer_ledger_entry_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained(
                        'customer_ledger_entries',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'customer_open_item_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained(
                        'customer_open_items',
                    )
                    ->restrictOnDelete();

                $table->string(
                    'adjustment_number',
                    160,
                )->nullable();

                $table->date(
                    'adjustment_date',
                );

                $table->date(
                    'posting_date',
                );

                $table->char(
                    'currency_code',
                    3,
                );

                $table->decimal(
                    'exchange_rate',
                    20,
                    8,
                )->default(1);

                $table->string(
                    'direction',
                    20,
                )->comment(
                    'debit, credit',
                );

                $table->string(
                    'customer_name',
                    160,
                );

                $table->string(
                    'customer_code',
                    60,
                );

                $table->string(
                    'offset_account_code',
                    50,
                );

                $table->string(
                    'offset_account_name',
                    160,
                );

                $table->string(
                    'status',
                    30,
                )
                    ->default('draft')
                    ->comment(
                        'draft, submitted, approved, posted, reversed, cancelled',
                    );

                $table->decimal(
                    'amount',
                    20,
                    6,
                );

                $table->decimal(
                    'base_amount',
                    20,
                    6,
                )->default(0);

                $table->string(
                    'reason',
                    500,
                );

                $table->text(
                    'notes',
                )->nullable();

                $table->unsignedInteger(
                    'revision',
                )->default(1);

                $table->foreignId(
                    'created_by_user_id',
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId(
                    'submitted_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp(
                    'submitted_at',
                )->nullable();

                $table->foreignId(
                    'approved_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp(
                    'approved_at',
                )->nullable();

                $table->foreignId(
                    'posted_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp(
                    'posted_at',
                )->nullable();

                $table->string(
                    'accounting_posting_reference',
                    190,
                )->nullable();

                $table->date(
                    'reversal_posting_date',
                )->nullable();

                $table->foreignId(
                    'reversed_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp(
                    'reversed_at',
                )->nullable();

                $table->string(
                    'reversal_reason',
                    500,
                )->nullable();

                $table->string(
                    'accounting_reversal_reference',
                    190,
                )->nullable();

                $table->foreignId(
                    'cancelled_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp(
                    'cancelled_at',
                )->nullable();

                $table->string(
                    'cancellation_reason',
                    500,
                )->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    [
                        'tenant_id',
                        'adjustment_number',
                    ],
                    'customer_ar_adjustments_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'posting_date',
                    ],
                    'customer_ar_adjustments_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_id',
                        'status',
                    ],
                    'customer_ar_adjustments_customer_status_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'customer_ar_adjustments',
        );

        Schema::dropIfExists(
            'customer_refund_allocations',
        );

        Schema::dropIfExists(
            'customer_refunds',
        );

        Schema::dropIfExists(
            'customer_credit_application_lines',
        );

        Schema::dropIfExists(
            'customer_credit_applications',
        );
    }
};