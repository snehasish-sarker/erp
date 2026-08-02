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
                    'invoiced_quantity',
                    20,
                    6,
                )
                    ->default(0)
                    ->after('accepted_quantity');
            },
        );

        Schema::create(
            'supplier_invoices',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('supplier_id')
                    ->constrained('suppliers')
                    ->restrictOnDelete();

                $table->foreignId('purchase_order_id')
                    ->constrained('purchase_orders')
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

                $table->string(
                    'supplier_invoice_number',
                    160,
                );

                $table->string(
                    'supplier_invoice_number_normalized',
                    190,
                );

                $table->char(
                    'supplier_invoice_number_hash',
                    64,
                );

                $table->date('invoice_date');
                $table->date('posting_date');
                $table->date('due_date');

                $table->char('currency_code', 3)
                    ->comment('ISO 4217 currency code');

                $table->decimal(
                    'exchange_rate',
                    20,
                    8,
                )->default(1);

                $table->string(
                    'supplier_name',
                    160,
                );

                $table->string(
                    'supplier_code',
                    60,
                );

                $table->string(
                    'supplier_tax_number',
                    100,
                )->nullable();

                $table->text(
                    'supplier_address',
                )->nullable();

                $table->unsignedSmallInteger(
                    'payment_terms_days',
                )->default(0);

                $table->string(
                    'purchase_order_number',
                    160,
                )->nullable();

                $table->decimal(
                    'total_invoiced_quantity',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_matched_quantity',
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
                    'other_charges',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'rounding_adjustment',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'quantity_variance',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'price_variance_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'discount_variance_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'tax_variance_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_variance_amount',
                    20,
                    6,
                )->default(0);

                $table->string('status', 30)
                    ->default('draft')
                    ->comment(
                        'draft, validated, approved, posted, disputed, reversed, cancelled',
                    );

                $table->string(
                    'match_status',
                    30,
                )
                    ->default('unmatched')
                    ->comment(
                        'unmatched, matched, variance, blocked',
                    );

                $table->text('notes')
                    ->nullable();

                $table->text('matching_notes')
                    ->nullable();

                $table->unsignedInteger('revision')
                    ->default(1);

                $table->timestamp(
                    'matching_reserved_at',
                )->nullable();

                $table->foreignId(
                    'created_by_user_id',
                )
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId(
                    'validated_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('validated_at')
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
                    'disputed_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('disputed_at')
                    ->nullable();

                $table->string(
                    'dispute_reason',
                    500,
                )->nullable();

                $table->foreignId(
                    'posted_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->timestamp('posted_at')
                    ->nullable();

                $table->string(
                    'accounting_posting_reference',
                    190,
                )->nullable();

                $table->date(
                    'reversal_posting_date',
                )->nullable();

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
                    'supplier_invoices_tenant_document_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'supplier_id',
                        'supplier_invoice_number_hash',
                    ],
                    'supplier_invoices_supplier_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                        'posting_date',
                    ],
                    'supplier_invoices_branch_status_date_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_id',
                        'status',
                    ],
                    'supplier_invoices_supplier_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'purchase_order_id',
                        'match_status',
                    ],
                    'supplier_invoices_po_match_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'due_date',
                        'status',
                    ],
                    'supplier_invoices_due_status_index',
                );
            },
        );

        Schema::create(
            'supplier_invoice_lines',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('supplier_invoice_id')
                    ->constrained('supplier_invoices')
                    ->cascadeOnDelete();

                $table->foreignId(
                    'purchase_order_line_id',
                )
                    ->constrained('purchase_order_lines')
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
                    'ordered_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'received_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'previously_invoiced_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'available_to_invoice_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'invoiced_quantity',
                    20,
                    6,
                );

                $table->decimal(
                    'matched_quantity',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'purchase_order_unit_price',
                    20,
                    6,
                );

                $table->decimal(
                    'invoice_unit_price',
                    20,
                    6,
                );

                $table->decimal(
                    'gross_amount',
                    20,
                    6,
                );

                $table->decimal(
                    'expected_discount_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'discount_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'purchase_order_tax_rate',
                    9,
                    6,
                )->default(0);

                $table->decimal(
                    'invoice_tax_rate',
                    9,
                    6,
                )->default(0);

                $table->decimal(
                    'expected_tax_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'tax_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'expected_line_total',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'line_total',
                    20,
                    6,
                );

                $table->decimal(
                    'quantity_variance',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'price_variance_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'discount_variance_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'tax_variance_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'total_variance_amount',
                    20,
                    6,
                )->default(0);

                $table->string(
                    'match_status',
                    30,
                )
                    ->default('unmatched')
                    ->comment(
                        'unmatched, matched, variance, blocked',
                    );

                $table->string(
                    'variance_reason',
                    500,
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'supplier_invoice_id',
                        'line_number',
                    ],
                    'supplier_invoice_lines_invoice_line_unique',
                );

                $table->unique(
                    [
                        'supplier_invoice_id',
                        'purchase_order_line_id',
                    ],
                    'supplier_invoice_lines_invoice_po_line_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'purchase_order_line_id',
                    ],
                    'supplier_invoice_lines_tenant_po_line_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                        'match_status',
                    ],
                    'supplier_invoice_lines_product_match_index',
                );
            },
        );

        Schema::create(
            'supplier_invoice_matches',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('supplier_invoice_id')
                    ->constrained('supplier_invoices')
                    ->cascadeOnDelete();

                $table->foreignId('supplier_invoice_line_id')
                    ->constrained('supplier_invoice_lines')
                    ->cascadeOnDelete();

                $table->foreignId('purchase_order_id')
                    ->constrained('purchase_orders')
                    ->restrictOnDelete();

                $table->foreignId('purchase_order_line_id')
                    ->constrained('purchase_order_lines')
                    ->restrictOnDelete();

                $table->foreignId('goods_receipt_id')
                    ->constrained('goods_receipts')
                    ->restrictOnDelete();

                $table->foreignId('goods_receipt_line_id')
                    ->constrained('goods_receipt_lines')
                    ->restrictOnDelete();

                $table->decimal(
                    'matched_quantity',
                    20,
                    6,
                );

                $table->decimal(
                    'receipt_accepted_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'previously_invoiced_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'available_quantity_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'purchase_order_unit_price_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'invoice_unit_price_snapshot',
                    20,
                    6,
                );

                $table->decimal(
                    'price_variance_per_unit',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'price_variance_amount',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'matched_amount',
                    20,
                    6,
                )->default(0);

                $table->timestamps();

                $table->unique(
                    [
                        'supplier_invoice_line_id',
                        'goods_receipt_line_id',
                    ],
                    'supplier_invoice_matches_line_receipt_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'goods_receipt_id',
                    ],
                    'supplier_invoice_matches_receipt_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'goods_receipt_line_id',
                    ],
                    'supplier_invoice_matches_receipt_line_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'purchase_order_line_id',
                    ],
                    'supplier_invoice_matches_po_line_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'supplier_invoice_matches',
        );

        Schema::dropIfExists(
            'supplier_invoice_lines',
        );

        Schema::dropIfExists(
            'supplier_invoices',
        );

        Schema::table(
            'goods_receipt_lines',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'invoiced_quantity',
                );
            },
        );
    }
};