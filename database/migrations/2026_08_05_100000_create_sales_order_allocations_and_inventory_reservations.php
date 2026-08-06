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
            'inventory_balances',
            function (Blueprint $table): void {
                $table->decimal(
                    'quantity_reserved',
                    20,
                    6,
                )
                    ->default(0)
                    ->after('quantity_on_hand');

                $table->index(
                    [
                        'tenant_id',
                        'warehouse_id',
                        'product_id',
                        'quantity_reserved',
                    ],
                    'inventory_balances_reserved_quantity_index',
                );
            },
        );

        Schema::create(
            'sales_order_allocations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
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

                /*
                 * MySQL allows multiple NULL values in a unique index.
                 * Only the current allocation carries this key; released
                 * and superseded revisions set it back to NULL.
                 */
                $table->string('active_key', 190)
                    ->nullable()
                    ->unique();

                $table->string('status', 30)
                    ->default('active')
                    ->comment(
                        'active, released, superseded',
                    );

                $table->unsignedInteger('revision');

                $table->text('notes')
                    ->nullable();

                $table->foreignId(
                    'created_by_user_id',
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId(
                    'released_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('released_at')
                    ->nullable();

                $table->string(
                    'release_reason',
                    500,
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'sales_order_id',
                        'revision',
                    ],
                    'sales_order_allocations_order_revision_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                    ],
                    'sales_order_allocations_branch_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'sales_order_id',
                        'status',
                    ],
                    'sales_order_allocations_order_status_index',
                );
            },
        );

        Schema::create(
            'sales_order_allocation_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId(
                    'sales_order_allocation_id',
                )
                    ->constrained(
                        'sales_order_allocations',
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'sales_order_line_id',
                )
                    ->constrained(
                        'sales_order_lines',
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
                    'requested_quantity',
                    20,
                    6,
                );

                $table->decimal(
                    'allocated_quantity',
                    20,
                    6,
                );

                $table->decimal(
                    'quantity_on_hand_snapshot',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'quantity_reserved_other_snapshot',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'quantity_available_snapshot',
                    20,
                    6,
                )->default(0);

                $table->timestamps();

                $table->unique(
                    [
                        'sales_order_allocation_id',
                        'sales_order_line_id',
                    ],
                    'sales_order_allocation_lines_order_line_unique',
                );

                $table->unique(
                    [
                        'sales_order_allocation_id',
                        'line_number',
                    ],
                    'sales_order_allocation_lines_line_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                    ],
                    'sales_order_allocation_lines_product_index',
                );
            },
        );

        Schema::create(
            'inventory_reservations',
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

                $table->foreignId(
                    'sales_order_allocation_line_id',
                )
                    ->nullable()
                    ->unique()
                    ->constrained(
                        'sales_order_allocation_lines',
                    )
                    ->restrictOnDelete();

                $table->string(
                    'reservation_key',
                    190,
                );

                /*
                 * Populated only while a reservation is open.
                 * This prevents duplicate active reservations
                 * for the same source.
                 */
                $table->string(
                    'active_key',
                    190,
                )
                    ->nullable()
                    ->unique();

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

                $table->decimal(
                    'reserved_quantity',
                    20,
                    6,
                );

                $table->decimal(
                    'consumed_quantity',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'released_quantity',
                    20,
                    6,
                )->default(0);

                $table->string('status', 30)
                    ->default('active')
                    ->comment(
                        'active, partially_consumed, consumed, released',
                    );

                $table->timestamp('reserved_at');

                $table->timestamp('expires_at')
                    ->nullable();

                $table->foreignId(
                    'created_by_user_id',
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId(
                    'released_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('released_at')
                    ->nullable();

                $table->string(
                    'release_reason',
                    500,
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'reservation_key',
                    ],
                    'inventory_reservations_key_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'warehouse_id',
                        'product_id',
                        'status',
                    ],
                    'inventory_reservations_location_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'source_type',
                        'source_id',
                    ],
                    'inventory_reservations_source_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'inventory_reservations',
        );

        Schema::dropIfExists(
            'sales_order_allocation_lines',
        );

        Schema::dropIfExists(
            'sales_order_allocations',
        );

        Schema::table(
            'inventory_balances',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'inventory_balances_reserved_quantity_index',
                );

                $table->dropColumn(
                    'quantity_reserved',
                );
            },
        );
    }
};