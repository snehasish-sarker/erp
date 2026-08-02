<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('tenant_id')
                ->constrained('tenants')
                ->restrictOnDelete();

            $table->string('name');
            $table->string('code', 50);

            $table->string('status', 30)
                ->default('active')
                ->comment('active, inactive, archived')
                ->index();

            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique([
                'tenant_id',
                'code',
            ]);

            $table->index([
                'tenant_id',
                'name',
            ]);

            $table->index([
                'tenant_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};