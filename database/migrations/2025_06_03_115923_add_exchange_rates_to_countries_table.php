<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->decimal('myr_exchange_rate', 10, 4)->default(1.0000)->after('currency_symbol')
                  ->comment('Rate to convert from local currency to Malaysian Ringgit');
            $table->decimal('usd_exchange_rate', 10, 4)->nullable()->after('myr_exchange_rate')
                  ->comment('Rate to convert from local currency to USD');
            $table->timestamp('exchange_rate_updated_at')->nullable()->after('usd_exchange_rate');
        });
    }

    public function down(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->dropColumn(['myr_exchange_rate', 'usd_exchange_rate', 'exchange_rate_updated_at']);
        });
    }
};
