<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_order_items', function (Blueprint $table) {
            $table->id('item_id');
            $table->unsignedBigInteger('job_order_id');
            $table->unsignedBigInteger('service_id')->nullable();
            $table->string('item_name', 150);
            $table->decimal('unit_price', 10, 2)->nullable();
            $table->enum('price_status', ['FIXED', 'TBA', 'QUOTED'])->default('FIXED');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('job_order_id');

            $table->foreign('job_order_id')
                  ->references('job_order_id')
                  ->on('job_orders')
                  ->cascadeOnDelete();

            $table->foreign('service_id')
                  ->references('service_id')
                  ->on('services')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_order_items');
    }
};
