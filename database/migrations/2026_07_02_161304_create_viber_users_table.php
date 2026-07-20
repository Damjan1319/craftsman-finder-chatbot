<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viber_users', function (Blueprint $table) {
            $table->id();
            $table->string('viber_id')->unique();
            $table->string('name')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('last_interaction')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viber_users');
    }
};
