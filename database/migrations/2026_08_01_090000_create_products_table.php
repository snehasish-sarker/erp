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
            'products',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('product_category_id')
                    ->constrained('product_categories')
                    ->restrictOnDelete();

                $table->foreignId('brand_id')
                    ->nullable()
                    ->constrained('brands')
                    ->restrictOnDelete();

                $table->foreignId('base_unit_id')
                    ->constrained('units')
                    ->restrictOnDelete();

                $table->string('name', 160);

                $table->string('sku', 80);

                $table->string('slug', 180);

                $table->string(
                    'barcode',
                    120,
                )->nullable();

                $table->string(
                    'product_type',
                    30,
                )
                    ->default('stock')
                    ->comment(
                        'stock, non_stock, service',
                    );

                $table->text('description')
                    ->nullable();

                $table->decimal(
                    'cost_price',
                    20,
                    6,
                )->default(0);

                $table->decimal(
                    'selling_price',
                    20,
                    6,
                )->default(0);

                $table->boolean('is_purchasable')
                    ->default(true);

                $table->boolean('is_sellable')
                    ->default(true);

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
                 * Identifiers remain reserved after soft deletion so old
                 * documents, imports, and inventory history stay unambiguous.
                 */
                $table->unique(
                    [
                        'tenant_id',
                        'sku',
                    ],
                    'products_tenant_sku_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'slug',
                    ],
                    'products_tenant_slug_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'barcode',
                    ],
                    'products_tenant_barcode_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_category_id',
                        'status',
                        'name',
                    ],
                    'products_tenant_category_status_name_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'brand_id',
                        'status',
                    ],
                    'products_tenant_brand_status_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'base_unit_id',
                    ],
                    'products_tenant_base_unit_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'product_type',
                        'status',
                    ],
                    'products_tenant_type_status_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};