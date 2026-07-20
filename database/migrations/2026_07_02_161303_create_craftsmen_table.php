<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('craftsmen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone');
            $table->string('viber_id')->nullable();
            $table->text('bio')->nullable();
            $table->string('city');
            $table->enum('status', ['active', 'inactive', 'pending'])->default('pending');
            $table->boolean('is_premium')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamp('subscription_expires_at')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'city', 'status']);
            $table->index(['status', 'subscription_expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('craftsmen');
    }
};
