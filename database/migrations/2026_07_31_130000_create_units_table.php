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
            'units',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->string('name', 100);

                $table->string('code', 30);

                $table->string(
                    'symbol',
                    20,
                )->nullable();

                $table->string(
                    'category',
                    30,
                )
                    ->default('count')
                    ->comment(
                        'count, weight, length, volume, area, time, other',
                    );

                $table->boolean(
                    'allow_decimal',
                )->default(false);

                $table->unsignedTinyInteger(
                    'decimal_places',
                )->default(0);

                $table->string(
                    'status',
                    20,
                )
                    ->default('active')
                    ->comment(
                        'active, inactive',
                    );

                $table->timestamps();
                $table->softDeletes();

                /*
                 * Unit codes remain reserved even after soft deletion.
                 * Historical documents may still reference the code.
                 */
                $table->unique(
                    [
                        'tenant_id',
                        'code',
                    ],
                    'units_tenant_code_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'status',
                        'name',
                    ],
                    'units_tenant_status_name_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'category',
                        'status',
                    ],
                    'units_tenant_category_status_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};