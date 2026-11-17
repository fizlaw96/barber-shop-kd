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
        Schema::create('queue_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barber_id')->nullable()->constrained()->nullOnDelete();
            $table->date('queue_date')->index();
            $table->unsignedInteger('number');
            $table->string('status')->default('waiting')->index();
            $table->boolean('is_free_cut')->default(false);
            $table->timestamp('called_at')->nullable();
            $table->timestamp('call_expires_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('served_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->unique(['queue_date', 'number']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_tickets');
    }
};
