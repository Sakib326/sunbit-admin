<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->longText('description')->nullable()->after('code');
            $table->boolean('is_top_destination')->default(false)->after('description');

            // Add index for top destinations for better query performance
            $table->index(['is_top_destination', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->dropIndex(['is_top_destination', 'status']);
            $table->dropColumn(['description', 'is_top_destination']);
        });
    }
};
