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
            'customer_dispatches',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('sales_order_id')
                    ->constrained('sales_orders')
                    ->restrictOnDelete();

                $table->foreignId('sales_order_allocation_id')
                    ->constrained('sales_order_allocations')
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

                $table->foreignId('document_number_allocation_id')
                    ->nullable()
                    ->unique()
                    ->constrained('document_number_allocations')
                    ->restrictOnDelete();

                $table->string('dispatch_number', 160)
                    ->nullable();

                /*
                 * Only an editable draft carries this key. MySQL permits
                 * multiple NULL values, so posted and reversed dispatches
                 * do not block later partial deliveries for the same order.
                 */
                $table->string('draft_key', 190)
                    ->nullable()
                    ->unique();

                $table->date('dispatch_date');

                $table->string('sales_order_number', 160);
                $table->string('customer_name', 160);
                $table->string('customer_code', 60);

                $table->string(
                    'customer_contact_person',
                    120,
                )->nullable();

                $table->string(
                    'customer_phone',
                    40,
                )->nullable();

                $table->text('shipping_address')
                    ->nullable();

                $table->text('delivery_instructions')
                    ->nullable();

                $table->string(
                    'carrier_name',
                    160,
                )->nullable();

                $table->string(
                    'vehicle_number',
                    80,
                )->nullable();

                $table->string(
                    'tracking_number',
                    120,
                )->nullable();

                $table->text('notes')
                    ->nullable();

                $table->string('status', 30)
                    ->default('draft')
                    ->comment(
                        'draft, posted, reversed',
                    );

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
                $table->softDeletes();

                $table->unique(
                    [
                        'tenant_id',
                        'dispatch_number',
                    ],
                    'customer_dispatches_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'dispatch_date',
                    ],
                    'customer_dispatches_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'sales_order_id',
                        'status',
                    ],
                    'customer_dispatches_order_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_id',
                        'status',
                    ],
                    'customer_dispatches_customer_status_index',
                );
            },
        );

        Schema::create(
            'customer_dispatch_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('customer_dispatch_id')
                    ->constrained('customer_dispatches')
                    ->cascadeOnDelete();

                $table->foreignId('sales_order_line_id')
                    ->constrained('sales_order_lines')
                    ->restrictOnDelete();

                $table->foreignId(
                    'sales_order_allocation_line_id',
                )
                    ->constrained(
                        'sales_order_allocation_lines',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'inventory_reservation_id',
                )
                    ->nullable()
                    ->constrained('inventory_reservations')
                    ->restrictOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('unit_id')
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->unsignedInteger('line_number');

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

                $table->text('description')
                    ->nullable();

                $table->decimal(
                    'dispatched_quantity',
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

                $table->foreignId(
                    'stock_ledger_entry_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained(
                        'stock_ledger_entries',
                    )
                    ->restrictOnDelete();

                $table->foreignId(
                    'reversal_stock_ledger_entry_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained(
                        'stock_ledger_entries',
                    )
                    ->restrictOnDelete();

                $table->timestamps();

                $table->unique(
                    [
                        'customer_dispatch_id',
                        'sales_order_line_id',
                    ],
                    'customer_dispatch_lines_dispatch_order_line_unique',
                );

                $table->unique(
                    [
                        'customer_dispatch_id',
                        'line_number',
                    ],
                    'customer_dispatch_lines_dispatch_line_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                    ],
                    'customer_dispatch_lines_tenant_product_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'customer_dispatch_lines',
        );

        Schema::dropIfExists(
            'customer_dispatches',
        );
    }
};