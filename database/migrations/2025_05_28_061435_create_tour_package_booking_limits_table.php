<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tour_package_booking_limits', function (Blueprint $table) {
            $table->id();
            $table->uuid('tour_package_id');
            $table->date('date');
            $table->unsignedInteger('max_booking')->nullable();
            $table->timestamps();

            $table->foreign('tour_package_id')->references('id')->on('tour_packages')->onDelete('cascade');
            $table->unique(['tour_package_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_package_booking_limits');
    }
};
