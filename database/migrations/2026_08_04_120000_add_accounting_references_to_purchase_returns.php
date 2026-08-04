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
            'purchase_returns',
            function (Blueprint $table): void {
                $table->string(
                    'accounting_reference',
                    160,
                )
                    ->nullable()
                    ->after('posted_at');

                $table->string(
                    'accounting_reversal_reference',
                    160,
                )
                    ->nullable()
                    ->after('reversed_at');
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'purchase_returns',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'accounting_reference',
                    'accounting_reversal_reference',
                ]);
            },
        );
    }
};