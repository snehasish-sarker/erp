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
            'supplier_open_items',
            function (Blueprint $table): void {
                $table->index(
                    [
                        'tenant_id',
                        'supplier_id',
                        'posting_date',
                        'due_date',
                    ],
                    'supplier_open_items_supplier_reporting_idx',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'posting_date',
                        'supplier_id',
                    ],
                    'supplier_open_items_branch_reporting_idx',
                );
            },
        );

        Schema::table(
            'supplier_open_item_allocations',
            function (Blueprint $table): void {
                $table->index(
                    [
                        'payable_open_item_id',
                        'posting_date',
                        'reversal_posting_date',
                    ],
                    'supplier_open_alloc_payable_reporting_idx',
                );

                $table->index(
                    [
                        'credit_open_item_id',
                        'posting_date',
                        'reversal_posting_date',
                    ],
                    'supplier_open_alloc_credit_reporting_idx',
                );
            },
        );

        Schema::table(
            'supplier_ledger_entries',
            function (Blueprint $table): void {
                $table->index(
                    [
                        'reversal_of_id',
                        'posting_date',
                    ],
                    'supplier_ledger_reversal_reporting_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'supplier_ledger_entries',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'supplier_ledger_reversal_reporting_idx',
                );
            },
        );

        Schema::table(
            'supplier_open_item_allocations',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'supplier_open_alloc_payable_reporting_idx',
                );

                $table->dropIndex(
                    'supplier_open_alloc_credit_reporting_idx',
                );
            },
        );

        Schema::table(
            'supplier_open_items',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'supplier_open_items_supplier_reporting_idx',
                );

                $table->dropIndex(
                    'supplier_open_items_branch_reporting_idx',
                );
            },
        );
    }
};