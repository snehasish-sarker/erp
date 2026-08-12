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
            'inventory_transfers',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('source_branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('destination_branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('source_warehouse_id')
                    ->constrained('warehouses')
                    ->restrictOnDelete();

                $table->foreignId('destination_warehouse_id')
                    ->constrained('warehouses')
                    ->restrictOnDelete();

                $table->foreignId('document_number_allocation_id')
                    ->nullable()
                    ->unique()
                    ->constrained('document_number_allocations')
                    ->restrictOnDelete();

                $table->string('transfer_number', 160)
                    ->nullable();

                $table->date('transfer_date');

                $table->string('status', 30)
                    ->default('draft')
                    ->comment('draft, posted, cancelled');

                $table->text('notes')->nullable();

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
                    ['tenant_id', 'transfer_number'],
                    'inventory_transfers_tenant_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'source_branch_id',
                        'status',
                        'transfer_date',
                    ],
                    'inventory_transfers_source_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'destination_branch_id',
                        'status',
                        'transfer_date',
                    ],
                    'inventory_transfers_dest_status_date_index',
                );
            },
        );

        Schema::create(
            'inventory_transfer_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('inventory_transfer_id')
                    ->constrained('inventory_transfers')
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

                $table->decimal('quantity', 20, 6);
                $table->decimal('unit_cost', 20, 6)
                    ->default(0);
                $table->decimal('transfer_value', 20, 6)
                    ->default(0);

                $table->timestamps();

                $table->unique(
                    ['inventory_transfer_id', 'line_number'],
                    'inventory_transfer_lines_transfer_line_unique',
                );

                $table->unique(
                    ['inventory_transfer_id', 'product_id'],
                    'inventory_transfer_lines_transfer_product_unique',
                );

                $table->index(
                    ['tenant_id', 'product_id'],
                    'inventory_transfer_lines_tenant_product_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transfer_lines');
        Schema::dropIfExists('inventory_transfers');
    }
};
