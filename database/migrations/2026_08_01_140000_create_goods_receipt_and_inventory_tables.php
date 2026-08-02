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
            'goods_receipts',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('purchase_order_id')
                    ->constrained('purchase_orders')
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
                    'receipt_number',
                    160,
                )->nullable();

                $table->date('receipt_date');

                $table->string(
                    'supplier_delivery_note',
                    160,
                )->nullable();

                /*
                 * Supplier snapshots preserve the original receiving
                 * document when Supplier master data changes later.
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

                $table->string('status', 30)
                    ->default('draft')
                    ->comment(
                        'draft, posted, reversed',
                    );

                $table->string(
                    'inspection_status',
                    30,
                )
                    ->default('not_required')
                    ->comment(
                        'not_required, pending, passed, partial, failed',
                    );

                $table->decimal(
                    'total_received_quantity',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_accepted_quantity',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_rejected_quantity',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_inventory_value',
                    20,
                    6,
                )->default(0);

                $table->text('notes')
                    ->nullable();

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

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'receipt_number',
                    ],
                    'goods_receipts_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'receipt_date',
                    ],
                    'goods_receipts_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'purchase_order_id',
                        'status',
                    ],
                    'goods_receipts_po_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_id',
                        'status',
                    ],
                    'goods_receipts_supplier_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'warehouse_id',
                        'status',
                    ],
                    'goods_receipts_warehouse_status_index',
                );
            },
        );

        Schema::create(
            'goods_receipt_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('goods_receipt_id')
                    ->constrained('goods_receipts')
                    ->cascadeOnDelete();

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
                    'ordered_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'previously_received_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'receipt_quantity',
                    20,
                    6,
                );

                $table->decimal(
                    'accepted_quantity',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'rejected_quantity',
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

                $table->string(
                    'batch_number',
                    120,
                )->nullable();

                $table->date(
                    'manufacturing_date',
                )->nullable();

                $table->date(
                    'expiry_date',
                )->nullable();

                $table->json(
                    'serial_numbers',
                )->nullable();

                $table->string(
                    'storage_location',
                    160,
                )->nullable();

                $table->string(
                    'variance_reason',
                    500,
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'goods_receipt_id',
                        'purchase_order_line_id',
                    ],
                    'goods_receipt_lines_receipt_po_line_unique',
                );

                $table->unique(
                    [
                        'goods_receipt_id',
                        'line_number',
                    ],
                    'goods_receipt_lines_receipt_line_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                    ],
                    'goods_receipt_lines_tenant_product_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'purchase_order_line_id',
                    ],
                    'goods_receipt_lines_tenant_po_line_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'batch_number',
                    ],
                    'goods_receipt_lines_tenant_batch_index',
                );
            },
        );

        Schema::create(
            'inventory_balances',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('warehouse_id')
                    ->constrained('warehouses')
                    ->restrictOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('unit_id')
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->decimal(
                    'quantity_on_hand',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'inventory_value',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'average_unit_cost',
                    20,
                    6,
                )->default(0);

                $table->unsignedBigInteger(
                    'version',
                )->default(0);

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'warehouse_id',
                        'product_id',
                    ],
                    'inventory_balances_location_product_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'product_id',
                    ],
                    'inventory_balances_branch_product_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                    ],
                    'inventory_balances_tenant_product_index',
                );
            },
        );

        Schema::create(
            'stock_ledger_entries',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('warehouse_id')
                    ->constrained('warehouses')
                    ->restrictOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('unit_id')
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->string(
                    'movement_type',
                    50,
                )->comment(
                    'goods_receipt, goods_receipt_reversal, purchase_return, dispatch, sales_return, transfer_in, transfer_out, adjustment_in, adjustment_out',
                );

                $table->string(
                    'posting_key',
                    190,
                );

                $table->string(
                    'source_type',
                    190,
                );

                $table->unsignedBigInteger(
                    'source_id',
                );

                $table->unsignedBigInteger(
                    'source_line_id',
                )->nullable();

                $table->string(
                    'document_number',
                    160,
                )->nullable();

                $table->dateTime(
                    'occurred_at',
                );

                $table->decimal(
                    'quantity_in',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'quantity_out',
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

                $table->decimal(
                    'balance_quantity',
                    20,
                    6,
                );

                $table->decimal(
                    'balance_value',
                    20,
                    6,
                );

                $table->foreignId(
                    'created_by_user_id',
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId(
                    'reversal_of_id',
                )
                    ->nullable()
                    ->constrained(
                        'stock_ledger_entries',
                    )
                    ->restrictOnDelete();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'posting_key',
                    ],
                    'stock_ledger_entries_posting_key_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'warehouse_id',
                        'product_id',
                        'occurred_at',
                    ],
                    'stock_ledger_entries_location_product_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'source_type',
                        'source_id',
                    ],
                    'stock_ledger_entries_source_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'movement_type',
                        'occurred_at',
                    ],
                    'stock_ledger_entries_movement_date_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'stock_ledger_entries',
        );

        Schema::dropIfExists(
            'inventory_balances',
        );

        Schema::dropIfExists(
            'goods_receipt_lines',
        );

        Schema::dropIfExists(
            'goods_receipts',
        );
    }
};