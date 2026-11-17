<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->string('status')->default('booked')->index();
            $table->string('source')->default('web');
            $table->text('notes')->nullable();
            $table->boolean('barber_was_random')->default(false);
            $table->unsignedTinyInteger('points_awarded')->default(0);
            $table->timestamp('day_reminder_sent_at')->nullable();
            $table->timestamp('hour_reminder_sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
