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
            'document_sequences',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->nullable()
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->string('scope_key', 80)
                    ->comment('company or branch:{branch_id}');

                $table->string('name', 120);

                $table->string('document_type', 60)
                    ->comment('purchase_requisition, request_for_quotation, purchase_order, goods_receipt, purchase_return, sales_quotation, sales_order, delivery_note, sales_invoice, sales_return, stock_transfer, stock_adjustment, customer_receipt, supplier_payment, journal_entry, debit_note, credit_note')
                    ->index();

                $table->string('prefix', 60)->nullable();
                $table->string('suffix', 60)->nullable();

                $table->unsignedBigInteger('current_number')
                    ->default(0);

                $table->unsignedTinyInteger('number_padding')
                    ->default(6);

                $table->string('reset_policy', 30)
                    ->default('never')
                    ->comment('never, calendar_year, fiscal_year')
                    ->index();

                $table->unsignedTinyInteger(
                    'fiscal_year_start_month',
                )->nullable();

                $table->string('last_reset_key', 20)->nullable();

                $table->string('status', 20)
                    ->default('active')
                    ->comment('active, inactive')
                    ->index();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'scope_key',
                        'document_type',
                    ],
                    'document_sequences_tenant_scope_type_unique',
                );

                $table->index([
                    'tenant_id',
                    'branch_id',
                    'status',
                ]);

                $table->index([
                    'tenant_id',
                    'document_type',
                    'status',
                ]);
            },
        );

        Schema::create(
            'document_number_allocations',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('document_sequence_id')
                    ->constrained('document_sequences')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->nullable()
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->string('document_type', 60)
                    ->comment('purchase_requisition, request_for_quotation, purchase_order, goods_receipt, purchase_return, sales_quotation, sales_order, delivery_note, sales_invoice, sales_return, stock_transfer, stock_adjustment, customer_receipt, supplier_payment, journal_entry, debit_note, credit_note');

                $table->string('reset_key', 20)
                    ->default('never');

                $table->unsignedBigInteger('sequence_number');
                $table->string('number', 160);
                $table->string('idempotency_key', 100);

                $table->string('allocatable_type')->nullable();
                $table->unsignedBigInteger('allocatable_id')->nullable();

                $table->timestamp('allocated_at');
                $table->timestamp('created_at')->useCurrent();

                $table->unique(
                    [
                        'tenant_id',
                        'idempotency_key',
                    ],
                    'document_allocations_tenant_idempotency_unique',
                );

                $table->unique(
                    [
                        'document_sequence_id',
                        'reset_key',
                        'sequence_number',
                    ],
                    'document_allocations_sequence_reset_number_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'document_type',
                        'number',
                    ],
                    'document_allocations_tenant_type_number_unique',
                );

                $table->index([
                    'tenant_id',
                    'branch_id',
                    'document_type',
                ]);

                $table->index(
                    [
                        'allocatable_type',
                        'allocatable_id',
                    ],
                    'document_allocations_allocatable_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('document_number_allocations');
        Schema::dropIfExists('document_sequences');
    }
};