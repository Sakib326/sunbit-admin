<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('booking_id');

            // Payment sequence for multiple payments
            $table->integer('payment_sequence')->default(1);
            $table->enum('payment_type', ['initial', 'partial', 'final', 'refund'])->default('initial');

            // Payment details
            $table->enum('payer_type', ['customer', 'agent', 'admin']);
            $table->foreignId('payer_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');

            // Payment gateways (Bangladesh + Malaysia + International)
            $table->enum('payment_method', [
                // Manual/POS
                'cash',
                'card_terminal',
                'bank_transfer',
                'cheque',

                // International
                'stripe',
                'paypal',
                'razorpay',

                // Bangladesh
                'bkash',
                'nagad',
                'rocket',
                'upay',
                'sslcommerz',
                'aamarpay',
                'portwallet',
                'shurjopay',

                // Malaysia
                'maybank2u',
                'cimb_clicks',
                'public_bank',
                'hong_leong_bank',
                'rhb_bank',
                'alliance_bank',
                'affin_bank',
                'bank_islam',
                'muamalat_bank',
                'touch_n_go',
                'grabpay',
                'boost',
                'fpx',
                'senangpay',
                'billplz',
                'ipay88',
                'molpay',

                // Others
                'adjustment'
            ]);

            // Gateway response
            $table->string('gateway_transaction_id')->nullable();
            $table->json('gateway_response')->nullable();
            $table->string('gateway_reference')->nullable();

            // Transaction tracking
            $table->string('payment_reference')->unique(); // PAY-2025-001
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'refunded']);
            $table->text('failure_reason')->nullable();

            // POS specific
            $table->string('receipt_number')->nullable();
            $table->string('terminal_id')->nullable();
            $table->text('payment_details')->nullable(); // Mobile number, account details, etc.
            $table->text('notes')->nullable();

            // Admin override
            $table->boolean('admin_override')->default(false);
            $table->text('override_reason')->nullable();

            // Audit
            $table->foreignId('processed_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('payment_date');
            $table->timestamp('gateway_callback_at')->nullable();

            $table->timestamps();

            $table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade');
            $table->index(['booking_id', 'payment_sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
