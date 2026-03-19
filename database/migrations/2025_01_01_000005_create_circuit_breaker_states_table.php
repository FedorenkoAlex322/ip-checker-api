<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('circuit_breaker_states', function (Blueprint $table) {
            $table->id();
            $table->string('service_name', 64)->unique();
            $table->string('state')->default('closed');
            $table->unsignedInteger('failure_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('circuit_breaker_states');
    }
};
