<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_backups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requested_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope', 40)->default('database_full')->comment('database_full');
            $table->string('initiated_by', 20)->default('manual')->comment('manual, scheduled');
            $table->string('database_connection', 60);
            $table->string('database_name', 160);
            $table->string('disk', 60);
            $table->string('path', 500);
            $table->string('filename', 255);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->char('checksum_sha256', 64)->nullable();
            $table->string('status', 24)->default('processing')->comment('processing, completed, failed, pruned');
            $table->string('verification_status', 24)->default('not_verified')->comment('not_verified, passed, failed');
            $table->string('verification_message', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('pruned_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'completed_at'], 'system_backups_status_completed_index');
            $table->index(['verification_status', 'verified_at'], 'system_backups_verification_index');
        });

        Schema::create('operations_runtime_states', function (Blueprint $table): void {
            $table->string('state_key', 120)->primary();
            $table->json('value')->nullable();
            $table->timestamp('touched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_runtime_states');
        Schema::dropIfExists('system_backups');
    }
};