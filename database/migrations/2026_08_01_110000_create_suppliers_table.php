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
            'suppliers',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->string('name', 160);

                $table->string('code', 60);

                $table->string(
                    'supplier_type',
                    30,
                )
                    ->default('company')
                    ->comment(
                        'company, individual, government, other',
                    );

                $table->string(
                    'contact_person',
                    120,
                )->nullable();

                $table->string('email')
                    ->nullable();

                $table->string('phone', 40)
                    ->nullable();

                $table->string(
                    'alternate_phone',
                    40,
                )->nullable();

                $table->string(
                    'tax_number',
                    100,
                )->nullable();

                $table->string(
                    'registration_number',
                    100,
                )->nullable();

                $table->string(
                    'address_line_1',
                    255,
                )->nullable();

                $table->string(
                    'address_line_2',
                    255,
                )->nullable();

                $table->string('city', 100)
                    ->nullable();

                $table->string('state', 100)
                    ->nullable();

                $table->string(
                    'postal_code',
                    30,
                )->nullable();

                $table->char(
                    'country_code',
                    2,
                )->nullable();

                $table->unsignedSmallInteger(
                    'payment_terms_days',
                )->default(0);

                $table->text('notes')
                    ->nullable();

                $table->string('status', 20)
                    ->default('active')
                    ->comment('active, inactive');

                $table->timestamps();
                $table->softDeletes();

                /*
                 * Supplier identifiers remain reserved after soft deletion
                 * so historical purchasing and payable references remain
                 * unambiguous.
                 */
                $table->unique(
                    [
                        'tenant_id',
                        'code',
                    ],
                    'suppliers_tenant_code_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'tax_number',
                    ],
                    'suppliers_tenant_tax_number_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'registration_number',
                    ],
                    'suppliers_tenant_registration_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'status',
                        'name',
                    ],
                    'suppliers_tenant_status_name_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'supplier_type',
                        'status',
                    ],
                    'suppliers_tenant_type_status_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};