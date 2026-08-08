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
            'user_notifications',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('tenant_id')
                    ->constrained('tenants')
                    ->restrictOnDelete();

                $table->foreignId('recipient_user_id')
                    ->constrained('users')
                    ->restrictOnDelete();

                $table->foreignId('actor_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->uuid('notification_key')
                    ->unique();

                $table->string(
                    'idempotency_key',
                    160,
                );

                $table->string(
                    'category',
                    40,
                )->comment(
                    'system, security, approval, procurement, inventory, sales, accounting, export',
                );

                $table->string(
                    'type',
                    100,
                )->index();

                $table->string(
                    'severity',
                    20,
                )
                    ->default('info')
                    ->comment(
                        'info, success, warning, error',
                    )
                    ->index();

                $table->string(
                    'title',
                    160,
                );

                $table->text('message');

                $table->string(
                    'action_url',
                    500,
                )->nullable();

                $table->string(
                    'action_label',
                    80,
                )->nullable();

                $table->string(
                    'source_type',
                    150,
                )->nullable();

                $table->string(
                    'source_id',
                    100,
                )->nullable();

                $table->string(
                    'actor_name',
                    120,
                )->nullable();

                $table->string(
                    'actor_email',
                    255,
                )->nullable();

                $table->json('data')
                    ->nullable();

                $table->timestamp('read_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'tenant_id',
                        'recipient_user_id',
                        'idempotency_key',
                    ],
                    'user_notifications_recipient_idempotency_unique',
                );

                $table->index(
                    [
                        'tenant_id',
                        'recipient_user_id',
                        'read_at',
                        'created_at',
                    ],
                    'user_notifications_recipient_read_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'category',
                        'created_at',
                    ],
                    'user_notifications_category_index',
                );

                $table->index(
                    [
                        'tenant_id',
                        'source_type',
                        'source_id',
                    ],
                    'user_notifications_source_index',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'user_notifications',
        );
    }
};