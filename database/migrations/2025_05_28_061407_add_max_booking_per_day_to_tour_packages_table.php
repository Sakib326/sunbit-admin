<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->unsignedInteger('max_booking_per_day')->nullable()->after('number_of_nights');
        });
    }

    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropColumn('max_booking_per_day');
        });
    }
};
