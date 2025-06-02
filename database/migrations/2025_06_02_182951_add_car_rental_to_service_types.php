<?php

// filepath: database/migrations/2025_06_03_100005_add_car_rental_to_service_types.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        // Add CAR_RENTAL to bookings service_type enum
        DB::statement("ALTER TABLE bookings MODIFY COLUMN service_type ENUM('FLIGHT', 'HOTEL', 'TRANSFER', 'TOURS', 'CRUISE', 'TRANSPORT', 'VISA', 'CAR_RENTAL')");

        // Add CAR_RENTAL to agent_commissions service enum
        DB::statement("ALTER TABLE agent_commissions MODIFY COLUMN service ENUM('FLIGHT', 'HOTEL', 'TRANSFER', 'TOURS', 'CRUISE', 'TRANSPORT', 'VISA', 'CAR_RENTAL')");
    }

    public function down(): void
    {
        // Remove CAR_RENTAL from enums
        DB::statement("ALTER TABLE bookings MODIFY COLUMN service_type ENUM('FLIGHT', 'HOTEL', 'TRANSFER', 'TOURS', 'CRUISE', 'TRANSPORT', 'VISA')");
        DB::statement("ALTER TABLE agent_commissions MODIFY COLUMN service ENUM('FLIGHT', 'HOTEL', 'TRANSFER', 'TOURS', 'CRUISE', 'TRANSPORT', 'VISA')");
    }
};
