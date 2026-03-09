<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id('vehicle_id');
            $table->unsignedBigInteger('customer_id');
            $table->string('plate_number', 20)->unique();
            $table->enum('vehicle_category', ['CAR', 'MOTOR']);
            $table->enum('vehicle_size', ['SMALL', 'MEDIUM', 'LARGE', 'XL'])->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('plate_number');
            $table->index('customer_id');

            $table->foreign('customer_id')
                  ->references('customer_id')
                  ->on('customers')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
