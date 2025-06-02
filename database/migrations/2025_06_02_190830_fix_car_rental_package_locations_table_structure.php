<?php

// filepath: database/migrations/2025_06_03_120001_fix_car_rental_package_locations_table_structure.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // First, drop the problematic table if it exists
        Schema::dropIfExists('car_rental_package_locations');

        // Recreate with the correct structure
        Schema::create('car_rental_package_locations', function (Blueprint $table) {
            $table->id();
            $table->uuid('car_rental_package_id');

            // Location hierarchy - a car can be available from multiple locations
            $table->foreignId('country_id')->nullable()->constrained('countries')->cascadeOnDelete();
            $table->foreignId('state_id')->nullable()->constrained('states')->cascadeOnDelete();
            $table->foreignId('zella_id')->nullable()->constrained('zellas')->cascadeOnDelete();
            $table->foreignId('upazilla_id')->nullable()->constrained('upazillas')->cascadeOnDelete();

            $table->timestamps();

            // Add foreign key constraint manually
            $table->foreign('car_rental_package_id')
                  ->references('id')
                  ->on('car_rental_packages')
                  ->onDelete('cascade');

            // Prevent duplicate location assignments
            $table->unique(['car_rental_package_id', 'country_id', 'state_id', 'zella_id', 'upazilla_id'], 'unique_car_location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_rental_package_locations');
    }
};
