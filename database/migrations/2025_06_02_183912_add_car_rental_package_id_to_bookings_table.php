<?php

// filepath: database/migrations/2025_06_03_100006_add_car_rental_package_id_to_bookings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->uuid('car_rental_package_id')->nullable()->after('tour_package_id');
            $table->index('car_rental_package_id');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['car_rental_package_id']);
            $table->dropColumn('car_rental_package_id');
        });
    }
};
