<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();

            $table->string('event', 50)
                ->comment(
                    'created, updated, deleted, restored, login, logout, password_reset, roles_changed, permissions_changed, approved, posted, reversed',
                )
                ->index();

            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();

            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();

            $table->string('request_id', 36)
                ->nullable()
                ->index();

            $table->string('route_name')->nullable();
            $table->string('http_method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('url')->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index([
                'tenant_id',
                'created_at',
            ]);

            $table->index([
                'tenant_id',
                'event',
                'created_at',
            ]);

            $table->index([
                'tenant_id',
                'subject_type',
                'subject_id',
            ]);

            $table->index([
                'tenant_id',
                'actor_user_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};