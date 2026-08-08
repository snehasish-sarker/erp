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
            'sales_orders',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
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
                    ->constrained(
                        'document_number_allocations',
                    )
                    ->restrictOnDelete();

                $table->string(
                    'document_number',
                    160,
                )->nullable();

                $table->date('order_date');

                $table->date(
                    'requested_delivery_date',
                )->nullable();

                $table->string(
                    'customer_reference',
                    120,
                )->nullable();

                $table->char(
                    'currency_code',
                    3,
                )->comment(
                    'ISO 4217 currency code',
                );

                $table->decimal(
                    'exchange_rate',
                    20,
                    8,
                )->default(1);

                $table->string(
                    'customer_name',
                    160,
                );

                $table->string(
                    'customer_code',
                    60,
                );

                $table->string(
                    'customer_type',
                    30,
                )->comment(
                    'company, individual, government, other',
                );

                $table->string(
                    'customer_contact_person',
                    120,
                )->nullable();

                $table->string(
                    'customer_email',
                )->nullable();

                $table->string(
                    'customer_phone',
                    40,
                )->nullable();

                $table->string(
                    'customer_tax_number',
                    100,
                )->nullable();

                $table->text(
                    'billing_address',
                )->nullable();

                $table->text(
                    'shipping_address',
                )->nullable();

                $table->unsignedSmallInteger(
                    'payment_terms_days',
                )->default(0);

                $table->decimal(
                    'credit_limit_snapshot',
                    20,
                    6,
                )->default(0);

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

                $table->text(
                    'delivery_instructions',
                )->nullable();

                $table->text(
                    'terms_and_conditions',
                )->nullable();

                $table->text(
                    'notes',
                )->nullable();

                $table->string(
                    'status',
                    30,
                )
                    ->default('draft')
                    ->comment(
                        'draft, submitted, approved, partially_allocated, allocated, partially_dispatched, dispatched, partially_invoiced, invoiced, closed, cancelled',
                    );

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
                        'document_number',
                    ],
                    'sales_orders_tenant_document_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'order_date',
                    ],
                    'sales_orders_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_id',
                        'status',
                    ],
                    'sales_orders_customer_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'warehouse_id',
                        'status',
                    ],
                    'sales_orders_warehouse_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'requested_delivery_date',
                        'status',
                    ],
                    'sales_orders_delivery_status_index',
                );
            },
        );

        Schema::create(
            'sales_order_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId(
                    'sales_order_id',
                )
                    ->constrained('sales_orders')
                    ->cascadeOnDelete();

                $table->foreignId(
                    'product_id',
                )
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId(
                    'unit_id',
                )
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

                $table->text(
                    'description',
                )->nullable();

                $table->decimal(
                    'ordered_quantity',
                    20,
                    6,
                );

                $table->decimal(
                    'allocated_quantity',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'dispatched_quantity',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'invoiced_quantity',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'returned_quantity',
                    20,
                    6,
                )->default(0);

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
                    9,
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

                $table->timestamps();

                $table->unique(
                    [
                        'sales_order_id',
                        'line_number',
                    ],
                    'sales_order_lines_order_line_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                    ],
                    'sales_order_lines_tenant_product_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'sales_order_id',
                    ],
                    'sales_order_lines_tenant_order_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'sales_order_lines',
        );

        Schema::dropIfExists(
            'sales_orders',
        );
    }
};