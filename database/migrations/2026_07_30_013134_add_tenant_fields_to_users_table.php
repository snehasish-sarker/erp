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
            $table->dropUnique(['email']);

            $table->foreignId('tenant_id')
                ->after('id')
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->string('status', 30)
                ->default('active')
                ->comment('active, inactive, suspended, archived')
                ->index();

            $table->unique([
                'tenant_id',
                'email',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique([
                'tenant_id',
                'email',
            ]);

            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('status');

            $table->unique('email');
        });
    }
};