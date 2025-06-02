<?php

// filepath: database/migrations/2025_06_03_100003_create_car_rental_booking_limits_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('car_rental_booking_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('car_rental_package_id')->constrained('car_rental_packages')->cascadeOnDelete();
            $table->date('date');

            // Override values for special dates
            $table->integer('max_booking_override')->nullable();
            $table->decimal('special_daily_price', 10, 2)->nullable();

            $table->timestamps();

            $table->unique(['car_rental_package_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_rental_booking_limits');
    }
};
