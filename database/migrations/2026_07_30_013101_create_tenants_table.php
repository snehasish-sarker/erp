<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table): void {
            $table->id();

            $table->string('name');
            $table->string('code', 50)->unique();
            $table->string('slug')->unique();

            $table->string('status', 30)
                ->default('active')
                ->comment('trial, active, suspended, past_due, cancelled, archived')
                ->index();

            $table->char('currency_code', 3)
                ->default('BDT')
                ->comment('ISO 4217 currency code');

            $table->string('timezone', 100)
                ->default('Asia/Dhaka');

            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->text('address')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};