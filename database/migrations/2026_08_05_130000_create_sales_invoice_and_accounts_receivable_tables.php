<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'customer_dispatches',
            function (Blueprint $table): void {
                $table->string(
                    'accounting_posting_reference',
                    190,
                )
                    ->nullable()
                    ->after('posted_at');

                $table->string(
                    'accounting_reversal_reference',
                    190,
                )
                    ->nullable()
                    ->after('reversal_reason');
            },
        );

        Schema::create(
            'sales_invoices',
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

                $table->foreignId('sales_order_id')
                    ->constrained('sales_orders')
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

                $table->string('invoice_number', 160)
                    ->nullable();

                /*
                 * Only an editable draft carries this key. Posted and
                 * reversed invoices set it to NULL so later partial invoices
                 * can be created for the same Sales Order.
                 */
                $table->string('draft_key', 190)
                    ->nullable()
                    ->unique();

                $table->date('invoice_date');
                $table->date('posting_date');
                $table->date('due_date');

                $table->string('sales_order_number', 160);
                $table->string('customer_name', 160);
                $table->string('customer_code', 60);
                $table->string('customer_type', 30);

                $table->string(
                    'customer_contact_person',
                    120,
                )->nullable();

                $table->string('customer_email')
                    ->nullable();

                $table->string('customer_phone', 40)
                    ->nullable();

                $table->string(
                    'customer_tax_number',
                    100,
                )->nullable();

                $table->text('billing_address')
                    ->nullable();

                $table->text('shipping_address')
                    ->nullable();

                $table->unsignedSmallInteger(
                    'payment_terms_days',
                )->default(0);

                $table->decimal(
                    'credit_limit_snapshot',
                    20,
                    6,
                )->default(0);

                $table->char('currency_code', 3);

                $table->decimal(
                    'exchange_rate',
                    20,
                    8,
                )->default(1);

                $table->decimal(
                    'subtotal',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'discount_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'tax_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'shipping_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'other_charges',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_cost',
                    20,
                    6,
                )->default(0);

                $table->text('notes')
                    ->nullable();

                $table->string('status', 30)
                    ->default('draft')
                    ->comment('draft, posted, reversed');

                $table->unsignedInteger('revision')
                    ->default(1);

                $table->foreignId(
                    'created_by_user_id',
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId(
                    'posted_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('posted_at')
                    ->nullable();

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

                $table->timestamp('reversed_at')
                    ->nullable();

                $table->string(
                    'reversal_reason',
                    500,
                )->nullable();

                $table->string(
                    'accounting_reversal_reference',
                    190,
                )->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    [
                        'tenant_id',
                        'invoice_number',
                    ],
                    'sales_invoices_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'posting_date',
                    ],
                    'sales_invoices_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_id',
                        'status',
                        'due_date',
                    ],
                    'sales_invoices_customer_status_due_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'sales_order_id',
                        'status',
                    ],
                    'sales_invoices_order_status_index',
                );
            },
        );

        Schema::create(
            'sales_invoice_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('sales_invoice_id')
                    ->constrained('sales_invoices')
                    ->cascadeOnDelete();

                $table->foreignId('sales_order_line_id')
                    ->constrained('sales_order_lines')
                    ->restrictOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('unit_id')
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->unsignedInteger('line_number');
                $table->string('product_name', 160);
                $table->string('product_sku', 80);

                $table->string('product_type', 30)
                    ->comment(
                        'stock, non_stock, service',
                    );

                $table->string('unit_name', 100);
                $table->string('unit_code', 30);
                $table->text('description')->nullable();

                $table->decimal(
                    'invoiced_quantity',
                    20,
                    6,
                );

                $table->decimal(
                    'unit_price',
                    20,
                    6,
                );

                $table->decimal(
                    'gross_amount',
                    20,
                    6,
                );

                $table->decimal(
                    'discount_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'tax_rate',
                    12,
                    6,
                )->default(0);

                $table->decimal(
                    'tax_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'line_total',
                    20,
                    6,
                );

                $table->decimal(
                    'unit_cost',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_cost',
                    20,
                    6,
                )->default(0);

                $table->timestamps();

                $table->unique(
                    [
                        'sales_invoice_id',
                        'sales_order_line_id',
                    ],
                    'sales_invoice_lines_invoice_order_line_unique',
                );

                $table->unique(
                    [
                        'sales_invoice_id',
                        'line_number',
                    ],
                    'sales_invoice_lines_invoice_line_unique',
                );
            },
        );

        Schema::create(
            'sales_invoice_dispatch_allocations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('sales_invoice_line_id')
                    ->constrained('sales_invoice_lines')
                    ->cascadeOnDelete();

                $table->foreignId('customer_dispatch_line_id')
                    ->constrained('customer_dispatch_lines')
                    ->restrictOnDelete();

                $table->decimal(
                    'allocated_quantity',
                    20,
                    6,
                );

                $table->decimal(
                    'unit_cost',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_cost',
                    20,
                    6,
                )->default(0);

                $table->timestamps();

                $table->unique(
                    [
                        'sales_invoice_line_id',
                        'customer_dispatch_line_id',
                    ],
                    'sales_invoice_dispatch_allocations_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_dispatch_line_id',
                    ],
                    'sales_invoice_dispatch_allocations_dispatch_index',
                );
            },
        );

        Schema::create(
            'customer_ledger_entries',
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

                $table->foreignId('accounting_period_id')
                    ->constrained('accounting_periods')
                    ->restrictOnDelete();

                $table->string('reference', 190);
                $table->string('posting_key', 190);
                $table->string('journal_reference', 190);

                $table->string('entry_type', 40)
                    ->comment(
                        'invoice, invoice_reversal, credit_note, credit_note_reversal, receipt, receipt_reversal, adjustment, adjustment_reversal',
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
                $table->char('currency_code', 3);

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

                $table->string('description', 500)
                    ->nullable();

                $table->foreignId('created_by_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('reversal_of_id')
                    ->nullable()
                    ->constrained('customer_ledger_entries')
                    ->restrictOnDelete();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'reference',
                    ],
                    'customer_ledger_tenant_reference_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'posting_key',
                    ],
                    'customer_ledger_tenant_posting_key_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'reversal_of_id',
                    ],
                    'customer_ledger_tenant_reversal_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_id',
                        'posting_date',
                    ],
                    'customer_ledger_customer_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'source_type',
                        'source_id',
                    ],
                    'customer_ledger_source_index',
                );
            },
        );

        Schema::create(
            'customer_open_items',
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

                $table->foreignId('accounting_period_id')
                    ->constrained('accounting_periods')
                    ->restrictOnDelete();

                $table->foreignId('customer_ledger_entry_id')
                    ->unique()
                    ->constrained('customer_ledger_entries')
                    ->restrictOnDelete();

                $table->string('item_type', 30)
                    ->comment(
                        'invoice, credit, receipt, adjustment',
                    );

                $table->string('source_type', 190);
                $table->unsignedBigInteger('source_id');

                $table->string('document_number', 160)
                    ->nullable();

                $table->date('document_date');
                $table->date('posting_date');
                $table->date('due_date')->nullable();
                $table->char('currency_code', 3);

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

                $table->timestamp('closed_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'source_type',
                        'source_id',
                        'item_type',
                    ],
                    'customer_open_items_source_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_id',
                        'status',
                        'due_date',
                    ],
                    'customer_open_items_customer_due_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'posting_date',
                    ],
                    'customer_open_items_branch_status_index',
                );
            },
        );

        Schema::create(
            'customer_open_item_allocations',
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

                $table->foreignId('accounting_period_id')
                    ->constrained('accounting_periods')
                    ->restrictOnDelete();

                $table->foreignId('receivable_open_item_id')
                    ->constrained('customer_open_items')
                    ->restrictOnDelete();

                $table->foreignId('credit_open_item_id')
                    ->constrained('customer_open_items')
                    ->restrictOnDelete();

                $table->string('allocation_type', 30)
                    ->comment(
                        'credit_note, receipt, manual, adjustment',
                    );

                $table->string('posting_key', 190);
                $table->string('source_type', 190)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->date('allocation_date');
                $table->date('posting_date');
                $table->char('currency_code', 3);

                $table->decimal('amount', 20, 6);

                $table->decimal(
                    'receivable_base_amount',
                    20,
                    6,
                );

                $table->decimal(
                    'credit_base_amount',
                    20,
                    6,
                );

                $table->decimal(
                    'exchange_difference_amount',
                    20,
                    6,
                )->default(0);

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

                $table->foreignId('reversal_accounting_period_id')
                    ->nullable()
                    ->constrained('accounting_periods')
                    ->restrictOnDelete();

                $table->date('reversal_posting_date')
                    ->nullable();

                $table->timestamp('reversed_at')
                    ->nullable();

                $table->string('reversal_reason', 500)
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'posting_key',
                    ],
                    'customer_open_alloc_tenant_key_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'receivable_open_item_id',
                        'status',
                    ],
                    'customer_open_alloc_receivable_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'credit_open_item_id',
                        'status',
                    ],
                    'customer_open_alloc_credit_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'customer_open_item_allocations',
        );

        Schema::dropIfExists(
            'customer_open_items',
        );

        Schema::dropIfExists(
            'customer_ledger_entries',
        );

        Schema::dropIfExists(
            'sales_invoice_dispatch_allocations',
        );

        Schema::dropIfExists(
            'sales_invoice_lines',
        );

        Schema::dropIfExists(
            'sales_invoices',
        );

        Schema::table(
            'customer_dispatches',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'accounting_posting_reference',
                    'accounting_reversal_reference',
                ]);
            },
        );
    }
};