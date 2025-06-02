<?php

// filepath: database/migrations/2025_06_03_180001_add_from_to_locations_to_tour_packages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            // FROM Location (Tour Start Point)
            $table->foreignId('from_country_id')->nullable()->after('agent_commission_percent')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('from_state_id')->nullable()->after('from_country_id')->constrained('states')->cascadeOnDelete();
            $table->foreignId('from_zella_id')->nullable()->after('from_state_id')->constrained('zellas')->cascadeOnDelete();
            $table->foreignId('from_upazilla_id')->nullable()->after('from_zella_id')->constrained('upazillas')->cascadeOnDelete();

            // TO Location (Tour Destination)
            $table->foreignId('to_country_id')->nullable()->after('from_upazilla_id')->constrained('countries')->cascadeOnDelete();
            $table->foreignId('to_state_id')->nullable()->after('to_country_id')->constrained('states')->cascadeOnDelete();
            $table->foreignId('to_zella_id')->nullable()->after('to_state_id')->constrained('zellas')->cascadeOnDelete();
            $table->foreignId('to_upazilla_id')->nullable()->after('to_zella_id')->constrained('upazillas')->cascadeOnDelete();

            // Additional location details
            $table->text('from_location_details')->nullable()->after('to_upazilla_id');
            $table->text('to_location_details')->nullable()->after('from_location_details');

            // Tour type
            $table->enum('tour_type', ['domestic', 'international', 'local'])->default('domestic')->after('to_location_details');
        });
    }

    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            // Drop foreign key constraints
            $table->dropForeign(['from_country_id']);
            $table->dropForeign(['from_state_id']);
            $table->dropForeign(['from_zella_id']);
            $table->dropForeign(['from_upazilla_id']);
            $table->dropForeign(['to_country_id']);
            $table->dropForeign(['to_state_id']);
            $table->dropForeign(['to_zella_id']);
            $table->dropForeign(['to_upazilla_id']);

            // Drop columns
            $table->dropColumn([
                'from_country_id', 'from_state_id', 'from_zella_id', 'from_upazilla_id',
                'to_country_id', 'to_state_id', 'to_zella_id', 'to_upazilla_id',
                'from_location_details', 'to_location_details', 'tour_type'
            ]);
        });
    }
};
