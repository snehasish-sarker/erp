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
            'brands',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->string('name', 120);

                $table->string('code', 40);

                $table->string('slug', 160);

                $table->string(
                    'website_url',
                    2048,
                )->nullable();

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
                 * Brand codes and slugs remain reserved after soft deletion
                 * so historical product references remain unambiguous.
                 */
                $table->unique(
                    [
                        'tenant_id',
                        'code',
                    ],
                    'brands_tenant_code_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'slug',
                    ],
                    'brands_tenant_slug_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'status',
                        'sort_order',
                        'name',
                    ],
                    'brands_tenant_status_order_name_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};