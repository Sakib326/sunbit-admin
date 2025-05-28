<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('tour_packages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('category_id'); // Foreign key to tour_categories
            $table->string('title');
            $table->string('slug');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->longText('description')->nullable();
            $table->longText('highlights')->nullable();
            $table->longText('tour_schedule')->nullable();
            $table->longText('whats_included')->nullable();
            $table->longText('whats_excluded')->nullable();
            $table->string('area_map_url')->nullable();
            $table->decimal('base_price_adult', 10, 2)->default(0);
            $table->decimal('base_price_child', 10, 2)->default(0);
            $table->decimal('agent_commission_percent', 5, 2)->default(0);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('tour_categories')->onDelete('cascade');
            $table->unique(['category_id', 'slug']); // Unique slug within category
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_packages');
    }
};
