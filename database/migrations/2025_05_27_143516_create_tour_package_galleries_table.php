<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tour_package_galleries', function (Blueprint $table) {
            $table->id();
            $table->uuid('tour_package_id');
            $table->string('image_url');
            $table->integer('position')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->foreign('tour_package_id')->references('id')->on('tour_packages')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_package_galleries');
    }
};
