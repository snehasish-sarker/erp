<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_plans', function (Blueprint $table): void {
            $table->string('billing_currency_code', 3)
                ->default('BDT')
                ->after('description');
            $table->unsignedTinyInteger('currency_scale')
                ->default(2)
                ->after('billing_currency_code');
            $table->unsignedBigInteger('monthly_price_minor')
                ->nullable()
                ->after('currency_scale');
            $table->unsignedBigInteger('annual_price_minor')
                ->nullable()
                ->after('monthly_price_minor');
        });

        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            $table->string('billing_cycle', 20)
                ->default('monthly')
                ->comment('monthly, annual')
                ->after('status');
            $table->string('billing_currency_code', 3)
                ->nullable()
                ->after('billing_cycle');
        });

        Schema::create('saas_invoice_counters', function (Blueprint $table): void {
            $table->id();
            $table->string('counter_key', 50);
            $table->string('period_key', 20);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            $table->unique(
                ['counter_key', 'period_key'],
                'saas_invoice_counter_unique',
            );
        });

        Schema::create('saas_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('tenant_subscription_id')
                ->constrained('tenant_subscriptions')
                ->cascadeOnDelete();
            $table->foreignId('saas_plan_id')
                ->constrained('saas_plans')
                ->restrictOnDelete();
            $table->foreignId('created_by_platform_admin_id')
                ->nullable()
                ->constrained('platform_admins')
                ->nullOnDelete();
            $table->string('invoice_number', 50)->unique();
            $table->string('status', 20)
                ->default('open')
                ->comment('open, paid, void, uncollectible')
                ->index();
            $table->string('billing_cycle', 20)
                ->comment('monthly, annual');
            $table->string('currency_code', 3);
            $table->unsignedTinyInteger('currency_scale')->default(2);
            $table->timestamp('period_starts_at');
            $table->timestamp('period_ends_at');
            $table->timestamp('issued_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('subtotal_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor');
            $table->unsignedBigInteger('amount_paid_minor')->default(0);
            $table->unsignedBigInteger('balance_due_minor');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['tenant_id', 'status', 'issued_at'],
                'saas_invoice_tenant_status_idx',
            );
            $table->index(
                ['tenant_subscription_id', 'period_starts_at'],
                'saas_invoice_subscription_period_idx',
            );
        });

        Schema::create('saas_invoice_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saas_invoice_id')
                ->constrained('saas_invoices')
                ->cascadeOnDelete();
            $table->string('description', 255);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('line_total_minor');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('saas_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('tenant_subscription_id')
                ->constrained('tenant_subscriptions')
                ->cascadeOnDelete();
            $table->foreignId('saas_invoice_id')
                ->nullable()
                ->constrained('saas_invoices')
                ->nullOnDelete();
            $table->foreignId('recorded_by_platform_admin_id')
                ->nullable()
                ->constrained('platform_admins')
                ->nullOnDelete();
            $table->string('provider', 50)
                ->default('manual')
                ->index();
            $table->string('provider_payment_id', 150)->nullable();
            $table->string('status', 20)
                ->default('pending')
                ->comment('pending, succeeded, failed, refunded, cancelled')
                ->index();
            $table->unsignedBigInteger('amount_minor');
            $table->string('currency_code', 3);
            $table->unsignedTinyInteger('currency_scale')->default(2);
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['provider', 'provider_payment_id'],
                'saas_payment_provider_ref_unique',
            );
            $table->index(
                ['tenant_id', 'status', 'created_at'],
                'saas_payment_tenant_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saas_payments');
        Schema::dropIfExists('saas_invoice_lines');
        Schema::dropIfExists('saas_invoices');
        Schema::dropIfExists('saas_invoice_counters');

        Schema::table('tenant_subscriptions', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_cycle',
                'billing_currency_code',
            ]);
        });

        Schema::table('saas_plans', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_currency_code',
                'currency_scale',
                'monthly_price_minor',
                'annual_price_minor',
            ]);
        });
    }
};
