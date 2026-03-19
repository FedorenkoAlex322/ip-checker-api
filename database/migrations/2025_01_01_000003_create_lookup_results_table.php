<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookup_results', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('api_key_id')->constrained('api_keys')->cascadeOnDelete();
            $table->string('target');
            $table->string('type');
            $table->string('provider', 64);
            $table->json('result_data');
            $table->float('lookup_time_ms');
            $table->boolean('cached')->default(false);
            $table->timestamps();

            $table->index(['target', 'type']);
            $table->index('api_key_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lookup_results');
    }
};
