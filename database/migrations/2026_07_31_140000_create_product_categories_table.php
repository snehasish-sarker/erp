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
            'product_categories',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('parent_id')
                    ->nullable()
                    ->constrained('product_categories')
                    ->restrictOnDelete();

                $table->string('name', 120);

                $table->string('code', 40);

                $table->string('slug', 160);

                $table->text('description')
                    ->nullable();

                $table->unsignedInteger(
                    'sort_order',
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
                 * Codes and slugs remain reserved after soft deletion so
                 * historical product and transaction references remain
                 * unambiguous.
                 */
                $table->unique(
                    [
                        'tenant_id',
                        'code',
                    ],
                    'product_categories_tenant_code_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'slug',
                    ],
                    'product_categories_tenant_slug_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'parent_id',
                        'status',
                        'sort_order',
                    ],
                    'product_categories_tree_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'status',
                        'name',
                    ],
                    'product_categories_status_name_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'product_categories',
        );
    }
};