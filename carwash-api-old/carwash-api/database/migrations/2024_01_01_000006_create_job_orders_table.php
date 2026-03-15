<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_orders', function (Blueprint $table) {
            $table->id('job_order_id');
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('vehicle_id');
            $table->string('washboy_name', 100)->nullable();
            $table->enum('payment_mode', ['CASH', 'GCASH', 'CARD', 'UNPAID'])->nullable();
            $table->enum('status', ['OPEN', 'IN_PROGRESS', 'DONE', 'CANCELLED'])->default('OPEN');
            $table->tinyInteger('leave_vehicle')->default(0);
            $table->tinyInteger('waiver_accepted')->default(0);
            $table->dateTime('waiver_accepted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('created_at');
            $table->index('vehicle_id');
            $table->index('customer_id');
            $table->index('payment_mode');

            $table->foreign('customer_id')
                  ->references('customer_id')
                  ->on('customers')
                  ->restrictOnDelete();

            $table->foreign('vehicle_id')
                  ->references('vehicle_id')
                  ->on('vehicles')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_orders');
    }
};
