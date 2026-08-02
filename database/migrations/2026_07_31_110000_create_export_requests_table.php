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
            'export_requests',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('requested_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('tenant_file_id')
                    ->nullable()
                    ->constrained('tenant_files')
                    ->nullOnDelete();

                $table->uuid('request_key');

                $table->string('name', 160);

                $table->string('export_type', 80)
                    ->comment(
                        'audit_logs and future registered export types',
                    )
                    ->index();

                $table->string('format', 20)
                    ->default('csv')
                    ->comment('csv')
                    ->index();

                $table->json('filters')->nullable();

                $table->string('status', 20)
                    ->default('queued')
                    ->comment(
                        'queued, processing, completed, failed, cancelled, expired',
                    )
                    ->index();

                $table->unsignedTinyInteger(
                    'progress_percent',
                )->default(0);

                $table->unsignedBigInteger(
                    'rows_exported',
                )->default(0);

                $table->string(
                    'error_code',
                    80,
                )->nullable();

                $table->text(
                    'error_message',
                )->nullable();

                $table->timestamp('queued_at');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('failed_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamp('expires_at')->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'request_key',
                    ],
                    'export_requests_tenant_request_key_unique',
                );

                $table->index([
                    'tenant_id',
                    'status',
                    'created_at',
                ]);

                $table->index([
                    'tenant_id',
                    'requested_by_user_id',
                    'created_at',
                ]);

                $table->index([
                    'tenant_id',
                    'export_type',
                    'created_at',
                ]);

                $table->index([
                    'status',
                    'expires_at',
                ]);
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('export_requests');
    }
};