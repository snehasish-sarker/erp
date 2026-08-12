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
            'inventory_stock_counts',
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
                    ->constrained('document_number_allocations')
                    ->restrictOnDelete();

                $table->string('count_number', 160)
                    ->nullable();

                $table->date('count_date');

                $table->string('status', 20)
                    ->default('draft')
                    ->comment('draft, posted, cancelled')
                    ->index();

                $table->text('notes')->nullable();

                $table->unsignedInteger('total_lines')
                    ->default(0);

                $table->unsignedInteger('variance_line_count')
                    ->default(0);

                $table->decimal('total_positive_variance', 20, 6)
                    ->default(0);

                $table->decimal('total_negative_variance', 20, 6)
                    ->default(0);

                $table->decimal('total_value_gain', 20, 6)
                    ->default(0);

                $table->decimal('total_value_loss', 20, 6)
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
                $table->string('cancellation_reason', 500)->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique(
                    ['tenant_id', 'count_number'],
                    'stock_counts_tenant_number_unique',
                );

                $table->index(
                    ['tenant_id', 'branch_id', 'status'],
                    'stock_counts_tenant_branch_status_idx',
                );

                $table->index(
                    ['tenant_id', 'warehouse_id', 'count_date'],
                    'stock_counts_tenant_wh_date_idx',
                );
            },
        );

        Schema::create(
            'inventory_stock_count_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('inventory_stock_count_id')
                    ->constrained('inventory_stock_counts')
                    ->cascadeOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('unit_id')
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->unsignedInteger('line_number');

                $table->string('product_name', 190);
                $table->string('product_sku', 120);
                $table->string('unit_name', 120);
                $table->string('unit_code', 60);

                $table->decimal('system_quantity', 20, 6)
                    ->default(0);

                $table->decimal('reserved_quantity', 20, 6)
                    ->default(0);

                $table->decimal('counted_quantity', 20, 6)
                    ->default(0);

                $table->decimal('variance_quantity', 20, 6)
                    ->default(0);

                $table->unsignedBigInteger('snapshot_ledger_entry_id')
                    ->nullable();

                $table->decimal('unit_cost', 20, 6)
                    ->default(0);

                $table->decimal('variance_value', 20, 6)
                    ->default(0);

                $table->unsignedBigInteger('stock_ledger_entry_id')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    ['inventory_stock_count_id', 'product_id'],
                    'stock_count_lines_count_product_unique',
                );

                $table->index(
                    ['tenant_id', 'product_id'],
                    'stock_count_lines_tenant_product_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_stock_count_lines');
        Schema::dropIfExists('inventory_stock_counts');
    }
};
