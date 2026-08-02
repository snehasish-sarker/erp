<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('tenant_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->softDeletes();

            $table->index([
                'tenant_id',
                'branch_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex([
                'tenant_id',
                'branch_id',
                'status',
            ]);

            $table->dropConstrainedForeignId('branch_id');
            $table->dropSoftDeletes();
        });
    }
};