<?php

// filepath: database/migrations/2025_06_03_160001_make_car_rental_detail_fields_nullable.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('car_rental_booking_details', function (Blueprint $table) {
            // Just make the unwanted fields nullable so they don't cause errors
            $table->foreignId('pickup_upazilla_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('car_rental_booking_details', function (Blueprint $table) {
            $table->foreignId('pickup_upazilla_id')->change();
        });
    }
};
