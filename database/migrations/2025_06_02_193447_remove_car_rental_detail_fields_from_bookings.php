<?php

// filepath: database/migrations/2025_06_03_140001_remove_car_rental_detail_fields_from_bookings.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Remove the unwanted fields from car_rental_booking_details
        Schema::table('car_rental_booking_details', function (Blueprint $table) {
            // Drop foreign key constraints first
            if (Schema::hasColumn('car_rental_booking_details', 'pickup_upazilla_id')) {
                $table->dropForeign(['pickup_upazilla_id']);
                $table->dropColumn([
                    'pickup_upazilla_id',
                    'pickup_address',
                    'driver_name',
                    'driver_phone'
                ]);
            }
        });
    }

    public function down(): void
    {
        // Restore the fields if needed
        Schema::table('car_rental_booking_details', function (Blueprint $table) {
            $table->foreignId('pickup_upazilla_id')->nullable()->constrained('upazillas')->cascadeOnDelete();
            $table->text('pickup_address')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
        });
    }
};
