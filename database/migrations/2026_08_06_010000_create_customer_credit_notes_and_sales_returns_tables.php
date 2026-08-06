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
            'sales_invoice_lines',
            function (Blueprint $table): void {
                $table->decimal(
                    'credited_quantity',
                    20,
                    6,
                )
                    ->default(0)
                    ->after('invoiced_quantity');

                $table->decimal(
                    'credited_amount',
                    20,
                    6,
                )
                    ->default(0)
                    ->after('line_total');

                $table->index(
                    [
                        'tenant_id',
                        'sales_invoice_id',
                        'credited_quantity',
                    ],
                    'sales_invoice_lines_credit_quantity_index',
                );
            },
        );

        Schema::table(
            'sales_order_lines',
            function (Blueprint $table): void {
                $table->decimal(
                    'returned_quantity',
                    20,
                    6,
                )
                    ->default(0)
                    ->after('invoiced_quantity');

                $table->index(
                    [
                        'tenant_id',
                        'sales_order_id',
                        'returned_quantity',
                    ],
                    'sales_order_lines_returned_quantity_index',
                );
            },
        );

        Schema::create(
            'customer_credit_notes',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('sales_invoice_id')
                    ->constrained('sales_invoices')
                    ->restrictOnDelete();

                $table->foreignId('sales_order_id')
                    ->constrained('sales_orders')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('warehouse_id')
                    ->nullable()
                    ->constrained('warehouses')
                    ->restrictOnDelete();

                $table->foreignId('customer_id')
                    ->constrained('customers')
                    ->restrictOnDelete();

                $table->foreignId(
                    'document_number_allocation_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained('document_number_allocations')
                    ->restrictOnDelete();

                $table->foreignId('customer_ledger_entry_id')
                    ->nullable()
                    ->unique()
                    ->constrained('customer_ledger_entries')
                    ->restrictOnDelete();

                $table->foreignId('customer_open_item_id')
                    ->nullable()
                    ->unique()
                    ->constrained('customer_open_items')
                    ->restrictOnDelete();

                $table->foreignId(
                    'customer_open_item_allocation_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained('customer_open_item_allocations')
                    ->restrictOnDelete();

                $table->string('credit_note_number', 160)
                    ->nullable();

                /*
                 * Only an editable credit note keeps this key. MySQL permits
                 * multiple NULL values, so posted, reversed, and cancelled
                 * documents do not block later partial credits.
                 */
                $table->string('draft_key', 190)
                    ->nullable()
                    ->unique();

                $table->date('credit_note_date');
                $table->date('posting_date');

                $table->string('sales_invoice_number', 160);
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

                $table->text('return_address')
                    ->nullable();

                $table->char('currency_code', 3);

                $table->decimal(
                    'exchange_rate',
                    20,
                    8,
                )->default(1);

                $table->decimal(
                    'gross_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'discount_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'subtotal',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'tax_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'quantity_credit_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'amount_only_credit_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'returned_quantity',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'inventory_return_value',
                    20,
                    6,
                )->default(0);

                $table->string('reason', 500);
                $table->text('notes')->nullable();

                $table->string('status', 30)
                    ->default('draft')
                    ->comment(
                        'draft, submitted, approved, posted, reversed, cancelled',
                    );

                $table->unsignedInteger('revision')
                    ->default(1);

                $table->foreignId('created_by_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('submitted_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('submitted_at')
                    ->nullable();

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

                $table->string(
                    'accounting_posting_reference',
                    190,
                )->nullable();

                $table->string(
                    'inventory_posting_reference',
                    190,
                )->nullable();

                $table->date('reversal_posting_date')
                    ->nullable();

                $table->foreignId('reversed_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('reversed_at')
                    ->nullable();

                $table->string('reversal_reason', 500)
                    ->nullable();

                $table->string(
                    'accounting_reversal_reference',
                    190,
                )->nullable();

                $table->string(
                    'inventory_reversal_reference',
                    190,
                )->nullable();

                $table->foreignId('cancelled_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('cancelled_at')
                    ->nullable();

                $table->string('cancellation_reason', 500)
                    ->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    [
                        'tenant_id',
                        'credit_note_number',
                    ],
                    'customer_credit_notes_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'posting_date',
                    ],
                    'customer_credit_notes_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'sales_invoice_id',
                        'status',
                    ],
                    'customer_credit_notes_invoice_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_id',
                        'status',
                    ],
                    'customer_credit_notes_customer_status_index',
                );
            },
        );

        Schema::create(
            'customer_credit_note_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('customer_credit_note_id')
                    ->constrained('customer_credit_notes')
                    ->cascadeOnDelete();

                $table->foreignId('sales_invoice_line_id')
                    ->constrained('sales_invoice_lines')
                    ->restrictOnDelete();

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

                $table->string('line_type', 20)
                    ->comment('quantity, amount');

                $table->string('product_name', 160);
                $table->string('product_sku', 80);

                $table->string('product_type', 30)
                    ->comment('stock, non_stock, service');

                $table->string('unit_name', 100);
                $table->string('unit_code', 30);
                $table->text('description')->nullable();

                $table->decimal(
                    'credit_quantity',
                    20,
                    6,
                )->default(0);

                $table->boolean('return_to_stock')
                    ->default(false);

                $table->decimal(
                    'unit_price',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'gross_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'discount_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'subtotal',
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
                )->default(0);

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

                $table->foreignId('stock_ledger_entry_id')
                    ->nullable()
                    ->unique()
                    ->constrained('stock_ledger_entries')
                    ->restrictOnDelete();

                $table->foreignId(
                    'reversal_stock_ledger_entry_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained('stock_ledger_entries')
                    ->restrictOnDelete();

                $table->timestamps();

                $table->unique(
                    [
                        'customer_credit_note_id',
                        'sales_invoice_line_id',
                    ],
                    'customer_credit_note_lines_invoice_line_unique',
                );

                $table->unique(
                    [
                        'customer_credit_note_id',
                        'line_number',
                    ],
                    'customer_credit_note_lines_line_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                        'return_to_stock',
                    ],
                    'customer_credit_note_lines_product_return_index',
                );
            },
        );

        Schema::create(
            'customer_credit_note_dispatch_allocations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('customer_credit_note_line_id')
                    ->constrained('customer_credit_note_lines')
                    ->cascadeOnDelete();

                $table->foreignId(
                    'sales_invoice_dispatch_allocation_id',
                )
                    ->constrained(
                        'sales_invoice_dispatch_allocations',
                    )
                    ->restrictOnDelete();

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
                        'customer_credit_note_line_id',
                        'sales_invoice_dispatch_allocation_id',
                    ],
                    'customer_credit_note_dispatch_allocations_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_dispatch_line_id',
                    ],
                    'customer_credit_note_dispatch_line_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'customer_credit_note_dispatch_allocations',
        );

        Schema::dropIfExists(
            'customer_credit_note_lines',
        );

        Schema::dropIfExists(
            'customer_credit_notes',
        );

        Schema::table(
            'sales_order_lines',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'sales_order_lines_returned_quantity_index',
                );

                $table->dropColumn(
                    'returned_quantity',
                );
            },
        );

        Schema::table(
            'sales_invoice_lines',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'sales_invoice_lines_credit_quantity_index',
                );

                $table->dropColumn([
                    'credited_quantity',
                    'credited_amount',
                ]);
            },
        );
    }
};