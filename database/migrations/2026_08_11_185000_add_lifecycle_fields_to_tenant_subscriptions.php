<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'tenant_subscriptions',
            function (Blueprint $table): void {
                $table->timestamp('current_period_starts_at')
                    ->nullable()
                    ->after('trial_ends_at');

                $table->timestamp('current_period_ends_at')
                    ->nullable()
                    ->after('current_period_starts_at');

                $table->timestamp('past_due_at')
                    ->nullable()
                    ->after('current_period_ends_at');

                $table->string('past_due_reason', 40)
                    ->nullable()
                    ->after('past_due_at')
                    ->comment('trial_expired, period_expired');

                $table->timestamp('grace_ends_at')
                    ->nullable()
                    ->after('past_due_reason');

                $table->timestamp('suspended_at')
                    ->nullable()
                    ->after('grace_ends_at');

                $table->string('suspension_reason', 40)
                    ->nullable()
                    ->after('suspended_at')
                    ->comment('manual, grace_expired');

                $table->timestamp('cancelled_at')
                    ->nullable()
                    ->after('suspension_reason');

                $table->timestamp('lifecycle_processed_at')
                    ->nullable()
                    ->after('cancelled_at');

                $table->index(
                    ['status', 'trial_ends_at'],
                    'tenant_sub_status_trial_idx',
                );

                $table->index(
                    ['status', 'current_period_ends_at'],
                    'tenant_sub_status_period_idx',
                );

                $table->index(
                    ['status', 'grace_ends_at'],
                    'tenant_sub_status_grace_idx',
                );
            },
        );
    }

    public function down(): void
    {
        Schema::table(
            'tenant_subscriptions',
            function (Blueprint $table): void {
                $table->dropIndex('tenant_sub_status_trial_idx');
                $table->dropIndex('tenant_sub_status_period_idx');
                $table->dropIndex('tenant_sub_status_grace_idx');

                $table->dropColumn([
                    'current_period_starts_at',
                    'current_period_ends_at',
                    'past_due_at',
                    'past_due_reason',
                    'grace_ends_at',
                    'suspended_at',
                    'suspension_reason',
                    'cancelled_at',
                    'lifecycle_processed_at',
                ]);
            },
        );
    }
};
