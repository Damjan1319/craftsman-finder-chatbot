<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->string('source')->nullable()->after('username');
        });

        Schema::table('viber_users', function (Blueprint $table) {
            $table->string('source')->nullable()->after('name');
        });

        Schema::create('usage_events', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->unsignedBigInteger('external_user_id')->nullable();
            $table->string('event', 50);
            $table->json('meta')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index(['platform', 'event', 'created_at']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_events');

        Schema::table('viber_users', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
