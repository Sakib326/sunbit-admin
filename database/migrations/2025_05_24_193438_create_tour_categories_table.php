<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tour_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name'); // Example: "Adventure Tours", "Family Getaways"
            $table->string('slug')->unique(); // Example: "adventure-tours"
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable(); // Example: "tours, adventure, family"
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_categories');
    }
};
