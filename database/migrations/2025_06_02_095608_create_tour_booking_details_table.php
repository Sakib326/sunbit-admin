<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tour_booking_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('booking_id')->constrained('bookings')->onDelete('cascade');

            $table->unsignedBigInteger('tour_package_id');

            // Tour-specific details
            $table->string('pickup_location')->nullable();
            $table->time('pickup_time')->default('08:00');
            $table->string('drop_location')->nullable();

            // Accommodation preferences
            $table->enum('room_type', ['single', 'twin', 'triple', 'family'])->default('twin');
            $table->enum('meal_plan', ['no_meals', 'breakfast', 'half_board', 'full_board'])->default('breakfast');

            // Tour preferences
            $table->string('guide_language')->default('English');
            $table->string('emergency_contact')->nullable();
            $table->text('tour_notes')->nullable();

            $table->timestamps();

            // Constraints
            $table->unique('booking_id'); // One tour detail per booking
            $table->index(['tour_package_id']);

            $table->foreign('tour_package_id')->references('id')->on('tour_packages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_booking_details');
    }
};
