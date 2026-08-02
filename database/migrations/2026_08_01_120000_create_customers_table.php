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
            'customers',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->string('name', 160);

                $table->string('code', 60);

                $table->string(
                    'customer_type',
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
                    'billing_address_line_1',
                    255,
                )->nullable();

                $table->string(
                    'billing_address_line_2',
                    255,
                )->nullable();

                $table->string(
                    'billing_city',
                    100,
                )->nullable();

                $table->string(
                    'billing_state',
                    100,
                )->nullable();

                $table->string(
                    'billing_postal_code',
                    30,
                )->nullable();

                $table->char(
                    'billing_country_code',
                    2,
                )->nullable();

                $table->string(
                    'shipping_address_line_1',
                    255,
                )->nullable();

                $table->string(
                    'shipping_address_line_2',
                    255,
                )->nullable();

                $table->string(
                    'shipping_city',
                    100,
                )->nullable();

                $table->string(
                    'shipping_state',
                    100,
                )->nullable();

                $table->string(
                    'shipping_postal_code',
                    30,
                )->nullable();

                $table->char(
                    'shipping_country_code',
                    2,
                )->nullable();

                $table->unsignedSmallInteger(
                    'payment_terms_days',
                )->default(0);

                $table->decimal(
                    'credit_limit',
                    20,
                    6,
                )->default(0);

                $table->text('notes')
                    ->nullable();

                $table->string('status', 20)
                    ->default('active')
                    ->comment('active, inactive');

                $table->timestamps();
                $table->softDeletes();

                /*
                 * Customer identifiers remain reserved after soft deletion
                 * so historical sales and receivable references remain
                 * unambiguous.
                 */
                $table->unique(
                    [
                        'tenant_id',
                        'code',
                    ],
                    'customers_tenant_code_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'tax_number',
                    ],
                    'customers_tenant_tax_number_unique',
                );

                $table->unique(
                    [
                        'tenant_id',
                        'registration_number',
                    ],
                    'customers_tenant_registration_number_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'status',
                        'name',
                    ],
                    'customers_tenant_status_name_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'customer_type',
                        'status',
                    ],
                    'customers_tenant_type_status_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};