<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('agent_commissions', function (Blueprint $table) {
            $table->id();
            $table->enum('service', [
                'FLIGHT',
                'HOTEL',
                'TRANSFER',
                'TOURS',
                'CRUISE',
                'TRANSPORT',
                'VISA',
            ]);
            $table->decimal('commission_percent', 5, 2)->nullable()->comment('Commission percentage for this service');
            $table->decimal('commission_amount', 10, 2)->nullable()->comment('Fixed commission amount for this service');
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete()->comment('If null, applies to all agents');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['service', 'agent_id'], 'unique_service_agent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commissions');
    }
};
