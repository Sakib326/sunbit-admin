<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        // For MySQL, we need to modify the enum
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_type ENUM('initial', 'partial', 'final', 'full_payment', 'partial_payment', 'advance_payment', 'refund') DEFAULT 'initial'");

        // Update existing data to new values
        DB::table('payments')->where('payment_type', 'initial')->update(['payment_type' => 'advance_payment']);
        DB::table('payments')->where('payment_type', 'partial')->update(['payment_type' => 'partial_payment']);
        DB::table('payments')->where('payment_type', 'final')->update(['payment_type' => 'full_payment']);
        // 'refund' stays the same

        // Remove old enum values
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_type ENUM('full_payment', 'partial_payment', 'advance_payment', 'refund') DEFAULT 'partial_payment'");
    }

    public function down(): void
    {
        // Reverse the changes
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_type ENUM('initial', 'partial', 'final', 'full_payment', 'partial_payment', 'advance_payment', 'refund') DEFAULT 'partial_payment'");

        // Revert data
        DB::table('payments')->where('payment_type', 'advance_payment')->update(['payment_type' => 'initial']);
        DB::table('payments')->where('payment_type', 'partial_payment')->update(['payment_type' => 'partial']);
        DB::table('payments')->where('payment_type', 'full_payment')->update(['payment_type' => 'final']);

        // Restore original enum
        DB::statement("ALTER TABLE payments MODIFY COLUMN payment_type ENUM('initial', 'partial', 'final', 'refund') DEFAULT 'initial'");
    }
};
