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
            'supplier_payments',
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

                $table->foreignId('payment_account_id')
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

                $table->string(
                    'payment_number',
                    160,
                )->nullable();

                $table->date('payment_date');

                $table->date('posting_date');

                $table->char(
                    'currency_code',
                    3,
                )->comment(
                    'ISO 4217 payment currency',
                );

                $table->decimal(
                    'exchange_rate',
                    20,
                    8,
                )->default(1);

                $table->string(
                    'payment_method',
                    40,
                )->comment(
                    'cash, bank_transfer, cheque, mobile_financial_service, other',
                );

                $table->string(
                    'payment_reference',
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
                    'supplier_name',
                    160,
                );

                $table->string(
                    'supplier_code',
                    60,
                );

                $table->string(
                    'payment_account_code',
                    50,
                );

                $table->string(
                    'payment_account_name',
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
                    'allocated_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'unallocated_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'base_total_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'base_allocated_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'base_unallocated_amount',
                    20,
                    6,
                )->default(0);

                $table->text('notes')
                    ->nullable();

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

                $table->foreignId(
                    'reversed_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->date(
                    'reversal_posting_date',
                )->nullable();

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
                        'payment_number',
                    ],
                    'supplier_payments_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_id',
                        'status',
                        'payment_date',
                    ],
                    'supplier_payments_supplier_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'posting_date',
                    ],
                    'supplier_payments_branch_status_posting_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'payment_account_id',
                        'posting_date',
                    ],
                    'supplier_payments_account_posting_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'payment_method',
                        'status',
                    ],
                    'supplier_payments_method_status_index',
                );
            },
        );

        Schema::create(
            'supplier_payment_allocations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId(
                    'supplier_payment_id',
                )
                    ->constrained(
                        'supplier_payments',
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'supplier_open_item_id',
                )
                    ->constrained(
                        'supplier_open_items',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'supplier_invoice_id',
                )
                    ->constrained(
                        'supplier_invoices',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'supplier_open_item_allocation_id',
                )->nullable();

                $table->unique(
                    'supplier_open_item_allocation_id',
                    'supplier_pay_open_item_alloc_uq',
                );

                $table->foreign(
                    'supplier_open_item_allocation_id',
                    'supplier_pay_open_item_alloc_fk',
                )
                    ->references('id')
                    ->on(
                        'supplier_open_item_allocations',
                    )
                    ->restrictOnDelete();

                $table->unsignedInteger(
                    'line_number',
                );

                $table->string(
                    'invoice_document_number',
                    160,
                )->nullable();

                $table->date(
                    'invoice_due_date',
                )->nullable();

                $table->char(
                    'currency_code',
                    3,
                );

                $table->decimal(
                    'invoice_exchange_rate',
                    20,
                    8,
                );

                $table->decimal(
                    'payment_exchange_rate',
                    20,
                    8,
                );

                $table->decimal(
                    'amount',
                    20,
                    6,
                );

                $table->decimal(
                    'payable_base_amount',
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
                    30,
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
                        'supplier_payment_id',
                        'line_number',
                    ],
                    'supplier_payment_allocations_line_unique',
                );

                $table->unique(
                    [
                        'supplier_payment_id',
                        'supplier_open_item_id',
                    ],
                    'supplier_payment_allocations_open_item_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_invoice_id',
                        'status',
                    ],
                    'supplier_payment_allocations_invoice_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_open_item_id',
                        'status',
                    ],
                    'supplier_payment_allocations_open_item_status_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'supplier_payment_allocations',
        );

        Schema::dropIfExists(
            'supplier_payments',
        );
    }
};