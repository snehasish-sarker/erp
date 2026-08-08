<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_close_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('run_number');
            $table->string('status', 24)->default('draft')
                ->comment('draft, ready, blocked, closed, reopened');
            $table->unsignedInteger('total_checks')->default(0);
            $table->unsignedInteger('passed_checks')->default(0);
            $table->unsignedInteger('warning_checks')->default(0);
            $table->unsignedInteger('failed_checks')->default(0);
            $table->decimal('total_reconciliation_difference', 20, 6)->default(0);
            $table->json('closing_journal_ids')->nullable();
            $table->text('close_reason')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->foreignId('prepared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'accounting_period_id', 'run_number'], 'period_close_runs_period_run_unique');
            $table->index(['tenant_id', 'status', 'created_at'], 'period_close_runs_status_index');
        });

        Schema::create('period_close_check_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_close_run_id')->constrained()->cascadeOnDelete();
            $table->string('check_key', 100);
            $table->string('category', 60);
            $table->string('label', 190);
            $table->string('status', 16)->comment('passed, warning, failed');
            $table->boolean('is_blocking')->default(true);
            $table->unsignedInteger('issue_count')->default(0);
            $table->decimal('difference_amount', 20, 6)->default(0);
            $table->text('message')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->unique(['period_close_run_id', 'check_key'], 'period_close_check_key_unique');
            $table->index(['tenant_id', 'status', 'is_blocking'], 'period_close_checks_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_close_check_items');
        Schema::dropIfExists('period_close_runs');
    }
};