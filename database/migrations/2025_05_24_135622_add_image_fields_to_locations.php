<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('flag')->nullable()->after('currency_symbol');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->string('image')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn('flag');
        });

        Schema::table('states', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
