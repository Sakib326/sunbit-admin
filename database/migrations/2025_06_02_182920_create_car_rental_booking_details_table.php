<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('car_rental_booking_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained('bookings')->cascadeOnDelete();
            $table->foreignUuid('car_rental_package_id')->constrained('car_rental_packages')->cascadeOnDelete();

            // Rental period
            $table->date('pickup_date');
            $table->date('return_date');
            $table->integer('rental_days');

            // Pickup location (from available locations)
            $table->foreignId('pickup_upazilla_id')->constrained('upazillas')->cascadeOnDelete();
            $table->text('pickup_address')->nullable();

            // Driver details
            $table->string('driver_name');
            $table->string('driver_phone');

            $table->timestamps();

            $table->unique('booking_id'); // One car rental per booking
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_rental_booking_details');
    }
};
