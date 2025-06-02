<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('car_rental_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Basic car info
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Car specifications
            $table->string('car_brand');
            $table->string('car_model');
            $table->enum('pax_capacity', ['1-4', '5-6', '7-8', '9-12']);
            $table->enum('transmission', ['automatic', 'manual']);
            $table->enum('air_condition', ['with_ac', 'without_ac']);
            $table->enum('chauffeur_option', ['with_chauffeur', 'without_chauffeur']);

            // Pricing & availability
            $table->decimal('daily_price', 10, 2);
            $table->decimal('agent_commission_percent', 5, 2)->nullable();
            $table->integer('total_cars')->default(1);

            // Media
            $table->string('featured_image')->nullable();

            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_rental_packages');
    }
};
