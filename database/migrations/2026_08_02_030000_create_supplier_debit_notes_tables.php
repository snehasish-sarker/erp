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
            'supplier_invoices',
            function (Blueprint $table): void {
                /*
                 * Amount reserved by approved but unposted
                 * Supplier Debit Notes.
                 */
                $table->decimal(
                    'debit_note_reserved_amount',
                    20,
                    6,
                )
                    ->default(0)
                    ->after('total_amount');

                /*
                 * Amount applied by posted Supplier Debit
                 * Notes.
                 */
                $table->decimal(
                    'debited_amount',
                    20,
                    6,
                )
                    ->default(0)
                    ->after(
                        'debit_note_reserved_amount',
                    );
            },
        );

        Schema::create(
            'supplier_debit_notes',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                /*
                 * One Purchase Return produces one Supplier
                 * Debit Note lifecycle. Reversal is recorded
                 * against the same Debit Note.
                 */
                $table->foreignId(
                    'purchase_return_id',
                )
                    ->unique()
                    ->constrained(
                        'purchase_returns',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'supplier_invoice_id',
                )
                    ->nullable()
                    ->constrained(
                        'supplier_invoices',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'purchase_order_id',
                )
                    ->constrained(
                        'purchase_orders',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'goods_receipt_id',
                )
                    ->constrained(
                        'goods_receipts',
                    )
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('supplier_id')
                    ->constrained('suppliers')
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
                    'debit_note_number',
                    160,
                )->nullable();

                $table->date(
                    'debit_note_date',
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

                /*
                 * Immutable source snapshots.
                 */
                $table->string(
                    'supplier_name',
                    160,
                );

                $table->string(
                    'supplier_code',
                    60,
                );

                $table->string(
                    'purchase_return_number',
                    160,
                )->nullable();

                $table->string(
                    'supplier_invoice_number',
                    160,
                )->nullable();

                $table->string(
                    'purchase_order_number',
                    160,
                )->nullable();

                $table->string(
                    'goods_receipt_number',
                    160,
                )->nullable();

                $table->unsignedInteger(
                    'source_purchase_return_revision',
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

                /*
                 * Sum of allocation rows currently reserved
                 * or applied to Supplier Invoices.
                 */
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

                /*
                 * Supplier commercial value from the source
                 * Purchase Return. This permits comparison
                 * with the Debit Note total including tax.
                 */
                $table->decimal(
                    'purchase_return_supplier_value',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'purchase_return_inventory_value',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'purchase_return_cost_variance',
                    20,
                    6,
                )->default(0);

                $table->string(
                    'supplier_reference',
                    160,
                )->nullable();

                $table->string(
                    'reason',
                    500,
                );

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
                        'debit_note_number',
                    ],
                    'supplier_debit_notes_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'debit_note_date',
                    ],
                    'supplier_debit_notes_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_id',
                        'status',
                    ],
                    'supplier_debit_notes_supplier_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_invoice_id',
                        'status',
                    ],
                    'supplier_debit_notes_invoice_status_index',
                );
            },
        );

        Schema::create(
            'supplier_debit_note_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId(
                    'supplier_debit_note_id',
                )
                    ->constrained(
                        'supplier_debit_notes',
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'purchase_return_line_id',
                )
                    ->constrained(
                        'purchase_return_lines',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'supplier_invoice_line_id',
                )
                    ->nullable()
                    ->constrained(
                        'supplier_invoice_lines',
                    )
                    ->restrictOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('unit_id')
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->unsignedInteger(
                    'line_number',
                );

                $table->string(
                    'product_name',
                    160,
                );

                $table->string(
                    'product_sku',
                    80,
                );

                $table->string(
                    'unit_name',
                    100,
                );

                $table->string(
                    'unit_code',
                    30,
                );

                $table->decimal(
                    'return_quantity',
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
                    'subtotal',
                    20,
                    6,
                );

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
                    'total_amount',
                    20,
                    6,
                );

                /*
                 * Source Purchase Return commercial and
                 * inventory valuation snapshots.
                 */
                $table->decimal(
                    'purchase_return_supplier_unit_cost',
                    20,
                    6,
                );

                $table->decimal(
                    'purchase_return_supplier_total_cost',
                    20,
                    6,
                );

                $table->decimal(
                    'purchase_return_inventory_unit_cost',
                    20,
                    6,
                );

                $table->decimal(
                    'purchase_return_inventory_total_cost',
                    20,
                    6,
                );

                $table->decimal(
                    'purchase_return_cost_variance',
                    20,
                    6,
                );

                $table->string(
                    'description',
                    500,
                )->nullable();

                $table->text('notes')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'supplier_debit_note_id',
                        'purchase_return_line_id',
                    ],
                    'supplier_debit_note_lines_return_line_unique',
                );

                $table->unique(
                    [
                        'supplier_debit_note_id',
                        'line_number',
                    ],
                    'supplier_debit_note_lines_line_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                    ],
                    'supplier_debit_note_lines_tenant_product_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_invoice_line_id',
                    ],
                    'supplier_debit_note_lines_invoice_line_index',
                );
            },
        );

        Schema::create(
            'supplier_debit_note_allocations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId(
                    'supplier_debit_note_id',
                )
                    ->constrained(
                        'supplier_debit_notes',
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'supplier_invoice_id',
                )
                    ->constrained(
                        'supplier_invoices',
                    )
                    ->restrictOnDelete();

                $table->decimal(
                    'amount',
                    20,
                    6,
                );

                $table->string(
                    'status',
                    30,
                )
                    ->default('draft')
                    ->comment(
                        'draft, reserved, applied, reversed, cancelled',
                    );

                $table->timestamp(
                    'reserved_at',
                )->nullable();

                $table->timestamp(
                    'applied_at',
                )->nullable();

                $table->timestamp(
                    'reversed_at',
                )->nullable();

                $table->timestamp(
                    'cancelled_at',
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'supplier_debit_note_id',
                        'supplier_invoice_id',
                    ],
                    'supplier_debit_note_allocations_note_invoice_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_invoice_id',
                        'status',
                    ],
                    'supplier_debit_note_allocations_invoice_status_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'supplier_debit_note_allocations',
        );

        Schema::dropIfExists(
            'supplier_debit_note_lines',
        );

        Schema::dropIfExists(
            'supplier_debit_notes',
        );

        Schema::table(
            'supplier_invoices',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'debit_note_reserved_amount',
                    'debited_amount',
                ]);
            },
        );
    }
};