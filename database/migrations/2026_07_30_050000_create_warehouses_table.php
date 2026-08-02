<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->foreignId('branch_id')
                ->constrained('branches')
                ->restrictOnDelete();

            $table->string('name');
            $table->string('code', 50);

            $table->string('type', 30)
                ->default('general')
                ->comment('general, transit, returns, damaged')
                ->index();

            $table->string('status', 30)
                ->default('active')
                ->comment('active, inactive, archived')
                ->index();

            $table->boolean('is_default')
                ->default(false)
                ->index();

            $table->text('address')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'tenant_id',
                'code',
            ]);

            $table->index([
                'tenant_id',
                'branch_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};