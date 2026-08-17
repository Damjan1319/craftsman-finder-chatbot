<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messenger_users', function (Blueprint $table) {
            $table->string('pending_category_slug')->nullable()->after('source');
        });

        Schema::table('instagram_users', function (Blueprint $table) {
            $table->string('pending_category_slug')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('messenger_users', function (Blueprint $table) {
            $table->dropColumn('pending_category_slug');
        });

        Schema::table('instagram_users', function (Blueprint $table) {
            $table->dropColumn('pending_category_slug');
        });
    }
};
