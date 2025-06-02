<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('car_rental_package_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('car_rental_package_id')->constrained('car_rental_packages')->cascadeOnDelete();

            // Location hierarchy - a car can be available from multiple locations
            $table->foreignId('country_id')->nullable()->constrained('countries')->cascadeOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->cascadeOnDelete();
            $table->foreignId('zella_id')->nullable()->constrained('zellas')->cascadeOnDelete();
            $table->foreignId('upazilla_id')->nullable()->constrained('upazillas')->cascadeOnDelete();

            $table->timestamps();

            // Prevent duplicate location assignments
            $table->unique(['car_rental_package_id', 'country_id', 'state_id', 'zella_id', 'upazilla_id'], 'unique_car_location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_rental_package_locations');
    }
};
