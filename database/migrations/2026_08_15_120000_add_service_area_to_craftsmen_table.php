<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('craftsmen', function (Blueprint $table) {
            $table->unsignedSmallInteger('service_radius_km')->nullable()->after('city');
            $table->decimal('latitude', 10, 7)->nullable()->after('service_radius_km');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::create('craftsman_service_cities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('craftsman_id')->constrained()->cascadeOnDelete();
            $table->string('city');
            $table->timestamps();

            $table->unique(['craftsman_id', 'city']);
            $table->index('city');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('craftsman_service_cities');

        Schema::table('craftsmen', function (Blueprint $table) {
            $table->dropColumn(['service_radius_km', 'latitude', 'longitude']);
        });
    }
};
