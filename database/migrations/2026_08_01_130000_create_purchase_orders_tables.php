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
            'purchase_orders',
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
                    'document_number',
                    160,
                )->nullable();

                $table->date('order_date');

                $table->date(
                    'expected_delivery_date',
                )->nullable();

                $table->string(
                    'supplier_reference',
                    120,
                )->nullable();

                $table->char('currency_code', 3)
                    ->comment('ISO 4217 currency code');

                $table->decimal(
                    'exchange_rate',
                    20,
                    8,
                )->default(1);

                /*
                 * Supplier snapshot fields preserve the document's original
                 * commercial identity even when the Supplier master record
                 * is updated later.
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
                    'supplier_contact_person',
                    120,
                )->nullable();

                $table->string(
                    'supplier_email',
                )->nullable();

                $table->string(
                    'supplier_phone',
                    40,
                )->nullable();

                $table->string(
                    'supplier_tax_number',
                    100,
                )->nullable();

                $table->text(
                    'supplier_address',
                )->nullable();

                $table->text(
                    'delivery_address',
                )->nullable();

                $table->unsignedSmallInteger(
                    'payment_terms_days',
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
                    'terms_and_conditions',
                )->nullable();

                $table->text('notes')
                    ->nullable();

                $table->string('status', 30)
                    ->default('draft')
                    ->comment(
                        'draft, submitted, approved, partially_received, received, closed, cancelled',
                    );

                $table->unsignedInteger('revision')
                    ->default(1);

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
                        'document_number',
                    ],
                    'purchase_orders_tenant_document_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'order_date',
                    ],
                    'purchase_orders_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_id',
                        'status',
                    ],
                    'purchase_orders_supplier_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'warehouse_id',
                        'status',
                    ],
                    'purchase_orders_warehouse_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'expected_delivery_date',
                        'status',
                    ],
                    'purchase_orders_delivery_status_index',
                );
            },
        );

        Schema::create(
            'purchase_order_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('purchase_order_id')
                    ->constrained('purchase_orders')
                    ->cascadeOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('unit_id')
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->unsignedInteger('line_number');

                /*
                 * Product and unit snapshots preserve the approved order's
                 * wording even when master-data names or codes change.
                 */
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
                    'ordered_quantity',
                    20,
                    6,
                );

                $table->decimal(
                    'received_quantity',
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
                        'purchase_order_id',
                        'line_number',
                    ],
                    'purchase_order_lines_order_line_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                    ],
                    'purchase_order_lines_tenant_product_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'purchase_order_id',
                    ],
                    'purchase_order_lines_tenant_order_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'purchase_order_lines',
        );

        Schema::dropIfExists(
            'purchase_orders',
        );
    }
};