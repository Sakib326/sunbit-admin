<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->decimal('agent_commission_percent', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tour_packages', function (Blueprint $table) {
            $table->decimal('agent_commission_percent', 5, 2)->default(0)->change();
        });
    }
};
