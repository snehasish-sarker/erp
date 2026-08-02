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
            'product_branch_settings',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->string('status', 20)
                    ->default('active')
                    ->comment('active, inactive');

                $table->boolean('is_purchasable')
                    ->default(true);

                $table->boolean('is_sellable')
                    ->default(true);

                $table->decimal(
                    'selling_price',
                    20,
                    6,
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'product_id',
                        'branch_id',
                    ],
                    'product_branch_settings_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                    ],
                    'product_branch_settings_branch_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                        'status',
                    ],
                    'product_branch_settings_product_status_index',
                );
            },
        );

        Schema::create(
            'product_warehouse_settings',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('branch_id')
                    ->constrained('branches')
                    ->restrictOnDelete();

                $table->foreignId('warehouse_id')
                    ->constrained('warehouses')
                    ->restrictOnDelete();

                $table->string('status', 20)
                    ->default('active')
                    ->comment('active, inactive');

                $table->decimal(
                    'minimum_stock',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'reorder_level',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'maximum_stock',
                    20,
                    6,
                )->nullable();

                $table->string(
                    'bin_location',
                    120,
                )->nullable();

                $table->boolean(
                    'allow_negative_stock',
                )->default(false);

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'product_id',
                        'warehouse_id',
                    ],
                    'product_warehouse_settings_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'branch_id',
                        'status',
                    ],
                    'product_warehouse_settings_branch_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'warehouse_id',
                        'status',
                    ],
                    'product_warehouse_settings_warehouse_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_id',
                        'status',
                    ],
                    'product_warehouse_settings_product_status_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'product_warehouse_settings',
        );

        Schema::dropIfExists(
            'product_branch_settings',
        );
    }
};