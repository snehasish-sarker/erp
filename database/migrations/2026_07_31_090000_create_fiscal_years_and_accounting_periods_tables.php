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
            'fiscal_years',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->string('name', 100);
                $table->string('code', 30);

                $table->date('start_date');
                $table->date('end_date');

                $table->string('status', 20)
                    ->default('active')
                    ->comment('active, closed')
                    ->index();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'code',
                    ],
                    'fiscal_years_tenant_code_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'start_date',
                        'end_date',
                    ],
                    'fiscal_years_tenant_dates_unique',
                );

                $table->index([
                    'tenant_id',
                    'start_date',
                    'end_date',
                ]);
            },
        );

        Schema::create(
            'accounting_periods',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('fiscal_year_id')
                    ->constrained('fiscal_years')
                    ->restrictOnDelete();

                $table->unsignedTinyInteger('period_number');

                $table->string('name', 100);
                $table->string('code', 40);

                $table->date('start_date');
                $table->date('end_date');

                $table->string('status', 20)
                    ->default('open')
                    ->comment('open, closed')
                    ->index();

                $table->timestamp('closed_at')->nullable();

                $table->foreignId('closed_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->unique(
                    [
                        'fiscal_year_id',
                        'period_number',
                    ],
                    'accounting_periods_year_number_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'code',
                    ],
                    'accounting_periods_tenant_code_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'start_date',
                        'end_date',
                    ],
                    'accounting_periods_tenant_dates_unique',
                );

                $table->index([
                    'tenant_id',
                    'status',
                    'start_date',
                    'end_date',
                ]);

                $table->index([
                    'tenant_id',
                    'fiscal_year_id',
                    'period_number',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_periods');
        Schema::dropIfExists('fiscal_years');
    }
};