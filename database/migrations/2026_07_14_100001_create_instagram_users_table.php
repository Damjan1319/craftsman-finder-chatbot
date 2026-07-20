<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_users', function (Blueprint $table) {
            $table->id();
            $table->string('igsid')->unique();
            $table->string('username')->nullable();
            $table->string('source')->nullable();
            $table->timestamp('last_interaction')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_users');
    }
};
