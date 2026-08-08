<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('management_budgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('fiscal_year_id')->constrained('fiscal_years')->restrictOnDelete();
            $table->string('name', 160);
            $table->char('currency_code', 3);
            $table->string('status', 20)->default('draft')->comment('draft, approved');
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(
                ['tenant_id', 'branch_id', 'fiscal_year_id'],
                'management_budgets_tenant_branch_year_unique',
            );
            $table->index(
                ['tenant_id', 'fiscal_year_id', 'status'],
                'management_budgets_year_status_index',
            );
        });

        Schema::create('management_budget_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('management_budget_id')->constrained('management_budgets')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('accounts')->restrictOnDelete();
            $table->unsignedTinyInteger('month_number');
            $table->decimal('amount', 20, 6)->default(0);
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(
                ['management_budget_id', 'account_id', 'month_number'],
                'management_budget_lines_account_month_unique',
            );
            $table->index(
                ['tenant_id', 'account_id', 'month_number'],
                'management_budget_lines_account_month_index',
            );
        });

        Schema::create('management_report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('report_type', 80);
            $table->string('format', 10)->default('xlsx')->comment('csv, xlsx');
            $table->string('frequency', 20)->comment('daily, weekly, monthly');
            $table->unsignedTinyInteger('run_day')->nullable()->comment('ISO weekday 1-7 for weekly, day 1-28 for monthly');
            $table->time('run_time')->default('07:00:00');
            $table->json('filters')->nullable();
            $table->string('status', 20)->default('active')->comment('active, inactive');
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 20)->nullable()->comment('queued, skipped, failed');
            $table->string('last_error', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(
                ['tenant_id', 'status', 'next_run_at'],
                'management_report_schedules_due_index',
            );
            $table->index(
                ['tenant_id', 'branch_id', 'report_type'],
                'management_report_schedules_report_index',
            );
        });

        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'branch_id', 'account_id', 'journal_entry_id'],
                'journal_lines_management_reporting_index',
            );
        });

        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->index(
                ['tenant_id', 'branch_id', 'supplier_id', 'status', 'posting_date'],
                'supplier_invoices_management_reporting_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table): void {
            $table->dropIndex('supplier_invoices_management_reporting_index');
        });

        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->dropIndex('journal_lines_management_reporting_index');
        });

        Schema::dropIfExists('management_report_schedules');
        Schema::dropIfExists('management_budget_lines');
        Schema::dropIfExists('management_budgets');
    }
};