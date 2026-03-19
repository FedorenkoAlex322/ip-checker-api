<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookup_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->nullable()->constrained('api_keys')->cascadeOnDelete();
            $table->string('method', 10);
            $table->string('endpoint');
            $table->string('target')->nullable();
            $table->string('type')->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->float('response_time_ms');
            $table->string('ip_address', 45);
            $table->string('user_agent', 500)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->timestamps();

            $table->index(['api_key_id', 'created_at']);
            $table->index('status_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_logs');
    }
};
