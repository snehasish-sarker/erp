<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saas_features', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('value_type', 20)
                ->default('boolean')
                ->comment('boolean, limit');
            $table->string('unit', 50)->nullable();
            $table->string('status', 20)
                ->default('active')
                ->comment('active, inactive')
                ->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('saas_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)
                ->default('active')
                ->comment('active, inactive')
                ->index();
            $table->boolean('is_default')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('saas_plan_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('saas_plan_id')
                ->constrained('saas_plans')
                ->cascadeOnDelete();
            $table->foreignId('saas_feature_id')
                ->constrained('saas_features')
                ->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedBigInteger('limit_value')->nullable()
                ->comment('Null means unlimited when enabled for limit features.');
            $table->timestamps();

            $table->unique(
                ['saas_plan_id', 'saas_feature_id'],
                'saas_plan_feature_unique',
            );
        });

        Schema::create('tenant_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')
                ->unique()
                ->constrained('tenants')
                ->cascadeOnDelete();
            $table->foreignId('saas_plan_id')
                ->constrained('saas_plans')
                ->restrictOnDelete();
            $table->foreignId('assigned_by_platform_admin_id')
                ->nullable()
                ->constrained('platform_admins')
                ->nullOnDelete();
            $table->string('status', 20)
                ->default('active')
                ->comment('trial, active, past_due, suspended, cancelled')
                ->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_subscriptions');
        Schema::dropIfExists('saas_plan_features');
        Schema::dropIfExists('saas_plans');
        Schema::dropIfExists('saas_features');
    }
};
