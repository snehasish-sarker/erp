<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'supplier_open_item_allocations',
            function (Blueprint $table): void {
                $table->renameColumn(
                    'base_amount',
                    'payable_base_amount',
                );
            },
        );

        Schema::table(
            'supplier_open_item_allocations',
            function (Blueprint $table): void {
                $table->decimal(
                    'credit_base_amount',
                    20,
                    6,
                )
                    ->default(0)
                    ->after('payable_base_amount');

                $table->decimal(
                    'exchange_difference_amount',
                    20,
                    6,
                )
                    ->default(0)
                    ->after('credit_base_amount')
                    ->comment(
                        'Payable base amount minus credit base amount; a signed realized exchange difference.',
                    );

                $table->foreignId(
                    'reversal_accounting_period_id',
                )
                    ->nullable()
                    ->after('reversed_by_user_id');

                $table->foreign(
                    'reversal_accounting_period_id',
                    'supplier_open_alloc_reversal_period_fk',
                )
                    ->references('id')
                    ->on('accounting_periods')
                    ->restrictOnDelete();

                $table->date(
                    'reversal_posting_date',
                )
                    ->nullable()
                    ->after(
                        'reversal_accounting_period_id',
                    );
            },
        );

        DB::table(
            'supplier_open_item_allocations',
        )->update([
            'credit_base_amount' => DB::raw(
                'payable_base_amount',
            ),
        ]);
    }

    public function down(): void
    {
        Schema::table(
            'supplier_open_item_allocations',
            function (Blueprint $table): void {
                $table->decimal(
                    'base_amount',
                    20,
                    6,
                )
                    ->default(0)
                    ->after('amount');
            },
        );

        DB::table(
            'supplier_open_item_allocations',
        )->update([
            'base_amount' => DB::raw(
                'payable_base_amount',
            ),
        ]);

        Schema::table(
            'supplier_open_item_allocations',
            function (Blueprint $table): void {
                $table->dropForeign(
                    'supplier_open_alloc_reversal_period_fk',
                );

                $table->dropColumn([
                    'payable_base_amount',
                    'credit_base_amount',
                    'exchange_difference_amount',
                    'reversal_accounting_period_id',
                    'reversal_posting_date',
                ]);
            },
        );
    }
};