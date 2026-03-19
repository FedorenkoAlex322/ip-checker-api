<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_key_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained('api_keys')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('request_count')->default(0);
            $table->timestamps();

            $table->unique(['api_key_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_usages');
    }
};
