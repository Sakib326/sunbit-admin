<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Service details
            $table->enum('service_type', ['FLIGHT', 'HOTEL', 'TRANSFER', 'TOURS', 'CRUISE', 'TRANSPORT', 'VISA']);
            $table->string('service_reference_id')->nullable(); // Points to tour_packages, hotels, etc.

            // Basic info
            $table->string('booking_reference')->unique(); // BK-2025-001
            $table->foreignId('customer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('booked_by')->constrained('users')->onDelete('cascade'); // Admin/Staff

            // Booking limits (max 6 adults, 6 children per email)
            $table->string('customer_email')->index();
            $table->integer('adults')->default(1);
            $table->integer('children')->default(0);

            // Pricing
            $table->decimal('original_price', 10, 2);
            $table->decimal('selling_price', 10, 2);
            $table->decimal('agent_discount_percent', 5, 2)->nullable();
            $table->decimal('agent_cost_price', 10, 2)->nullable(); // What agent pays
            $table->decimal('additional_charges', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('final_amount', 10, 2); // Total amount

            // Payment tracking
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2)->default(0);
            $table->boolean('allow_partial_payment')->default(false);
            $table->decimal('minimum_payment_amount', 10, 2)->nullable();

            // Customer details
            $table->string('customer_name');
            $table->string('customer_phone');
            $table->string('customer_passport_number')->nullable();
            $table->text('special_requirements')->nullable();

            // Service details
            $table->date('service_date')->nullable();

            // Status
            $table->enum('booking_source', ['admin_pos', 'agent', 'website', 'mobile_app'])->default('admin_pos');
            $table->enum('status', ['draft', 'confirmed', 'cancelled', 'completed'])->default('confirmed');
            $table->enum('payment_status', ['pending', 'partial', 'paid', 'refunded', 'zero_payment'])->default('pending');

            // Admin options
            $table->boolean('admin_override_payment')->default(false);
            $table->text('payment_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->json('booking_details')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['customer_email', 'service_reference_id', 'service_date']);
            $table->index(['service_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
