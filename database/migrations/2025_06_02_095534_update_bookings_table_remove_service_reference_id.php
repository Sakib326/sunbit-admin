<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Remove service_reference_id and its index
            $table->dropIndex(['customer_email', 'service_reference_id', 'service_date']);
            $table->dropColumn('service_reference_id');

            // Add CAR_RENTAL to service_type enum
            DB::statement("ALTER TABLE bookings MODIFY COLUMN service_type ENUM('FLIGHT', 'HOTEL', 'TRANSFER', 'TOURS', 'CRUISE', 'TRANSPORT', 'VISA', 'CAR_RENTAL')");

            // Add service_end_date for multi-day bookings
            $table->date('service_end_date')->nullable()->after('service_date');

            // Add new index without service_reference_id
            $table->index(['customer_email', 'service_date']);
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            // Restore service_reference_id
            $table->string('service_reference_id')->nullable()->after('service_type');
            $table->dropColumn('service_end_date');

            // Restore original index
            $table->dropIndex(['customer_email', 'service_date']);
            $table->index(['customer_email', 'service_reference_id', 'service_date']);
        });

        // Revert service_type enum
        DB::statement("ALTER TABLE bookings MODIFY COLUMN service_type ENUM('FLIGHT', 'HOTEL', 'TRANSFER', 'TOURS', 'CRUISE', 'TRANSPORT', 'VISA')");
    }
};
