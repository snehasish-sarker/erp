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
            'inventory_adjustments',
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

                $table->foreignId('document_number_allocation_id')
                    ->nullable()
                    ->unique()
                    ->constrained('document_number_allocations')
                    ->restrictOnDelete();

                $table->string('adjustment_number', 160)
                    ->nullable();

                $table->date('adjustment_date');

                $table->string('status', 30)
                    ->default('draft')
                    ->comment('draft, posted, cancelled');

                $table->string('reason', 500);
                $table->text('notes')->nullable();

                $table->decimal('total_quantity_in', 20, 6)
                    ->default(0);
                $table->decimal('total_quantity_out', 20, 6)
                    ->default(0);
                $table->decimal('total_value_in', 20, 6)
                    ->default(0);
                $table->decimal('total_value_out', 20, 6)
                    ->default(0);

                $table->foreignId('created_by_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('posted_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('posted_at')->nullable();

                $table->foreignId('cancelled_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancellation_reason', 500)
                    ->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['tenant_id', 'adjustment_number'],
                    'inventory_adjustments_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'adjustment_date',
                    ],
                    'inventory_adjustments_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'warehouse_id',
                        'status',
                    ],
                    'inventory_adjustments_warehouse_status_index',
                );
            },
        );

        Schema::create(
            'inventory_adjustment_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('inventory_adjustment_id')
                    ->constrained('inventory_adjustments')
                    ->cascadeOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('unit_id')
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->unsignedInteger('line_number');
                $table->string('product_name', 190);
                $table->string('product_sku', 100);
                $table->string('unit_name', 120);
                $table->string('unit_code', 50);

                $table->string('adjustment_type', 20)
                    ->comment('increase, decrease');

                $table->decimal('quantity', 20, 6);
                $table->decimal('unit_cost', 20, 6)
                    ->default(0);
                $table->decimal('adjustment_value', 20, 6)
                    ->default(0);
                $table->decimal('quantity_before', 20, 6)
                    ->default(0);
                $table->decimal('quantity_after', 20, 6)
                    ->default(0);

                $table->foreignId('stock_ledger_entry_id')
                    ->nullable()
                    ->unique()
                    ->constrained('stock_ledger_entries')
                    ->restrictOnDelete();

                $table->timestamps();

                $table->unique(
                    ['inventory_adjustment_id', 'line_number'],
                    'inventory_adjustment_lines_adjustment_line_unique',
                );

                $table->unique(
                    ['inventory_adjustment_id', 'product_id'],
                    'inventory_adjustment_lines_adjustment_product_unique',
                );

                $table->index(
                    ['tenant_id', 'product_id', 'adjustment_type'],
                    'inventory_adjustment_lines_product_type_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustment_lines');
        Schema::dropIfExists('inventory_adjustments');
    }
};
