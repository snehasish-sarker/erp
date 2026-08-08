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
            'production_acceptance_runs',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->uuid('uuid')
                    ->unique();

                $table->string(
                    'status',
                    20,
                )
                    ->default('running')
                    ->comment(
                        'running, passed, blocked, failed',
                    )
                    ->index();

                $table->string(
                    'environment',
                    50,
                );

                $table->string(
                    'source',
                    20,
                )
                    ->default('web')
                    ->comment(
                        'web, cli',
                    );

                $table->unsignedInteger(
                    'total_checks',
                )->default(0);

                $table->unsignedInteger(
                    'passed_checks',
                )->default(0);

                $table->unsignedInteger(
                    'warning_checks',
                )->default(0);

                $table->unsignedInteger(
                    'failed_checks',
                )->default(0);

                $table->unsignedInteger(
                    'blocking_failures',
                )->default(0);

                $table->json(
                    'summary',
                )->nullable();

                $table->foreignId(
                    'started_by_user_id',
                )
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamp(
                    'started_at',
                );

                $table->timestamp(
                    'completed_at',
                )->nullable();

                $table->timestamps();

                $table->index(
                    [
                        'tenant_id',
                        'status',
                        'completed_at',
                    ],
                    'production_acceptance_runs_status_idx',
                );
            },
        );

        Schema::create(
            'production_acceptance_check_items',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId(
                    'production_acceptance_run_id',
                );

                $table->foreign(
                    'production_acceptance_run_id',
                    'prod_accept_check_run_fk',
                )
                    ->references('id')
                    ->on(
                        'production_acceptance_runs',
                    )
                    ->cascadeOnDelete();

                $table->unsignedInteger(
                    'sequence',
                );

                $table->string(
                    'category',
                    50,
                )->index();

                $table->string(
                    'check_key',
                    160,
                );

                $table->string(
                    'label',
                    190,
                );

                $table->string(
                    'status',
                    20,
                )
                    ->comment(
                        'passed, warning, failed',
                    )
                    ->index();

                $table->boolean(
                    'blocking',
                )
                    ->default(false)
                    ->index();

                $table->text(
                    'message',
                );

                $table->json(
                    'context',
                )->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'production_acceptance_run_id',
                        'check_key',
                    ],
                    'production_acceptance_check_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'category',
                        'status',
                    ],
                    'production_acceptance_checks_status_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'production_acceptance_check_items',
        );

        Schema::dropIfExists(
            'production_acceptance_runs',
        );
    }
};