<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->string('guide_pdf_url')->nullable()->after('area_map_url');
            $table->boolean('is_featured')->default(false)->after('guide_pdf_url');
            $table->boolean('is_popular')->default(false)->after('is_featured');
            $table->unsignedInteger('number_of_days')->default(1)->after('is_popular');
            $table->unsignedInteger('number_of_nights')->default(0)->after('number_of_days');
        });
    }

    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->dropColumn(['guide_pdf_url', 'is_featured', 'is_popular', 'number_of_days', 'number_of_nights']);
        });
    }
};
