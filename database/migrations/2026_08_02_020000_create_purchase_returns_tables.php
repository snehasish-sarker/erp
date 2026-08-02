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
            'goods_receipt_lines',
            function (Blueprint $table): void {
                $table->decimal(
                    'return_reserved_quantity',
                    20,
                    6,
                )
                    ->default(0)
                    ->after('accepted_quantity');

                $table->decimal(
                    'returned_quantity',
                    20,
                    6,
                )
                    ->default(0)
                    ->after(
                        'return_reserved_quantity',
                    );

                $table->index(
                    [
                        'tenant_id',
                        'goods_receipt_id',
                        'returned_quantity',
                    ],
                    'goods_receipt_lines_returned_quantity_index',
                );
            },
        );

        Schema::create(
            'purchase_returns',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
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

                $table->foreignId(
                    'supplier_invoice_id',
                )
                    ->nullable()
                    ->constrained(
                        'supplier_invoices',
                    )
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('warehouse_id')
                    ->nullable()
                    ->constrained('warehouses')
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
                    'return_number',
                    160,
                )->nullable();

                $table->date('return_date');
                $table->date('posting_date');

                $table->string(
                    'supplier_reference',
                    160,
                )->nullable();

                /*
                 * Snapshots preserve the commercial source
                 * document when related master records change.
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
                    'purchase_order_number',
                    160,
                )->nullable();

                $table->string(
                    'goods_receipt_number',
                    160,
                )->nullable();

                $table->string(
                    'supplier_invoice_number',
                    160,
                )->nullable();

                $table->string('status', 30)
                    ->default('draft')
                    ->comment(
                        'draft, submitted, approved, posted, reversed, cancelled',
                    );

                $table->decimal(
                    'total_return_quantity',
                    20,
                    6,
                )->default(0);

                /*
                 * Commercial value expected from the
                 * supplier debit-note workflow.
                 */
                $table->decimal(
                    'total_supplier_value',
                    20,
                    6,
                )->default(0);

                /*
                 * Weighted-average inventory value removed
                 * when the return is posted.
                 */
                $table->decimal(
                    'total_inventory_value',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_cost_variance',
                    20,
                    6,
                )->default(0);

                $table->string(
                    'return_reason',
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

                $table->timestamp('submitted_at')
                    ->nullable();

                $table->foreignId(
                    'approved_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('approved_at')
                    ->nullable();

                $table->foreignId(
                    'posted_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('posted_at')
                    ->nullable();

                $table->foreignId(
                    'reversed_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->date(
                    'reversal_posting_date',
                )->nullable();

                $table->timestamp('reversed_at')
                    ->nullable();

                $table->string(
                    'reversal_reason',
                    500,
                )->nullable();

                $table->foreignId(
                    'cancelled_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('cancelled_at')
                    ->nullable();

                $table->string(
                    'cancellation_reason',
                    500,
                )->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    [
                        'tenant_id',
                        'return_number',
                    ],
                    'purchase_returns_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'return_date',
                    ],
                    'purchase_returns_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'goods_receipt_id',
                        'status',
                    ],
                    'purchase_returns_receipt_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_id',
                        'status',
                    ],
                    'purchase_returns_supplier_status_index',
                );
            },
        );

        Schema::create(
            'purchase_return_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId(
                    'purchase_return_id',
                )
                    ->constrained(
                        'purchase_returns',
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'goods_receipt_line_id',
                )
                    ->constrained(
                        'goods_receipt_lines',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'purchase_order_line_id',
                )
                    ->constrained(
                        'purchase_order_lines',
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
                    'product_type',
                    30,
                )->comment(
                    'stock, non_stock, service',
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
                    'accepted_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'previously_returned_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'previously_reserved_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'returnable_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'return_quantity',
                    20,
                    6,
                );

                /*
                 * Supplier value comes from the original
                 * receipt commercial cost.
                 */
                $table->decimal(
                    'supplier_unit_cost',
                    20,
                    6,
                );

                $table->decimal(
                    'supplier_total_cost',
                    20,
                    6,
                );

                /*
                 * Inventory value is assigned at posting
                 * using the current weighted-average cost.
                 */
                $table->decimal(
                    'inventory_unit_cost',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'inventory_total_cost',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'cost_variance_amount',
                    20,
                    6,
                )->default(0);

                $table->string(
                    'batch_number',
                    120,
                )->nullable();

                $table->json(
                    'serial_numbers',
                )->nullable();

                $table->string(
                    'return_reason',
                    500,
                )->nullable();

                $table->text('notes')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'purchase_return_id',
                        'goods_receipt_line_id',
                    ],
                    'purchase_return_lines_return_receipt_line_unique',
                );

                $table->unique(
                    [
                        'purchase_return_id',
                        'line_number',
                    ],
                    'purchase_return_lines_return_line_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                    ],
                    'purchase_return_lines_tenant_product_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'goods_receipt_line_id',
                    ],
                    'purchase_return_lines_tenant_receipt_line_index',
                );
            },
        );

        Schema::table(
            'stock_ledger_entries',
            function (Blueprint $table): void {
                $table->string(
                    'movement_type',
                    50,
                )
                    ->comment(
                        'goods_receipt, goods_receipt_reversal, purchase_return, purchase_return_reversal, dispatch, sales_return, transfer_in, transfer_out, adjustment_in, adjustment_out',
                    )
                    ->change();
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'stock_ledger_entries',
            function (Blueprint $table): void {
                $table->string(
                    'movement_type',
                    50,
                )
                    ->comment(
                        'goods_receipt, goods_receipt_reversal, purchase_return, dispatch, sales_return, transfer_in, transfer_out, adjustment_in, adjustment_out',
                    )
                    ->change();
            },
        );

        Schema::dropIfExists(
            'purchase_return_lines',
        );

        Schema::dropIfExists(
            'purchase_returns',
        );

        Schema::table(
            'goods_receipt_lines',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'goods_receipt_lines_returned_quantity_index',
                );

                $table->dropColumn([
                    'return_reserved_quantity',
                    'returned_quantity',
                ]);
            },
        );
    }
};