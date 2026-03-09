<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_pricing', function (Blueprint $table) {
            $table->id('pricing_id');
            $table->unsignedBigInteger('service_id');
            $table->enum('vehicle_size', ['SMALL', 'MEDIUM', 'LARGE', 'XL'])->nullable();
            $table->decimal('price', 10, 2);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('service_id')
                  ->references('service_id')
                  ->on('services')
                  ->cascadeOnDelete();

            // One price per service/size pair.
            $table->unique(['service_id', 'vehicle_size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_pricing');
    }
};
