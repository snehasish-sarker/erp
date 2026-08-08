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
            'customer_receipts',
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

                $table->foreignId('receipt_account_id')
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
                    'receipt_number',
                    160,
                )->nullable();

                $table->date('receipt_date');

                $table->date('posting_date');

                $table->char(
                    'currency_code',
                    3,
                )->comment(
                    'ISO 4217 receipt currency',
                );

                $table->decimal(
                    'exchange_rate',
                    20,
                    8,
                )->default(1);

                $table->string(
                    'receipt_method',
                    40,
                )->comment(
                    'cash, bank_transfer, cheque, mobile_financial_service, other',
                );

                $table->string(
                    'receipt_reference',
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
                    'receipt_account_code',
                    50,
                );

                $table->string(
                    'receipt_account_name',
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
                        'receipt_number',
                    ],
                    'customer_receipts_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_id',
                        'status',
                        'receipt_date',
                    ],
                    'customer_receipts_customer_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'posting_date',
                    ],
                    'customer_receipts_branch_status_posting_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'receipt_account_id',
                        'posting_date',
                    ],
                    'customer_receipts_account_posting_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'receipt_method',
                        'status',
                    ],
                    'customer_receipts_method_status_index',
                );
            },
        );

        Schema::create(
            'customer_receipt_allocations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId(
                    'customer_receipt_id',
                )
                    ->constrained(
                        'customer_receipts',
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'customer_open_item_id',
                )
                    ->constrained(
                        'customer_open_items',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'sales_invoice_id',
                )
                    ->constrained(
                        'sales_invoices',
                    )
                    ->restrictOnDelete();

                /*
                 * Keep the column definition simple here.
                 *
                 * UNIQUE and FOREIGN KEY constraints are defined separately
                 * below with short explicit names to stay within MySQL's
                 * 64-character identifier limit.
                 */
                $table->foreignId(
                    'customer_open_item_allocation_id',
                )->nullable();

                $table->unique(
                    'customer_open_item_allocation_id',
                    'cust_receipt_open_alloc_uq',
                );

                $table->foreign(
                    'customer_open_item_allocation_id',
                    'cust_receipt_open_alloc_fk',
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
                    'receipt_exchange_rate',
                    20,
                    8,
                );

                $table->decimal(
                    'amount',
                    20,
                    6,
                );

                $table->decimal(
                    'receivable_base_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'receipt_base_amount',
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
                        'customer_receipt_id',
                        'line_number',
                    ],
                    'customer_receipt_allocations_line_unique',
                );

                $table->unique(
                    [
                        'customer_receipt_id',
                        'customer_open_item_id',
                    ],
                    'customer_receipt_allocations_open_item_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'sales_invoice_id',
                        'status',
                    ],
                    'customer_receipt_allocations_invoice_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_open_item_id',
                        'status',
                    ],
                    'customer_receipt_allocations_open_item_status_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'customer_receipt_allocations',
        );

        Schema::dropIfExists(
            'customer_receipts',
        );
    }
};