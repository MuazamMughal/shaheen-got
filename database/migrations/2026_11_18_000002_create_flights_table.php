<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One record per group block. A return trip is still ONE record (one leg):
        // departure_date = travel date, arrival_date = return date.
        Schema::create('flights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('sector_id')->constrained()->restrictOnDelete();
            $table->foreignId('airline_id')->constrained()->restrictOnDelete();
            $table->foreignId('baggage_type_id')->constrained()->restrictOnDelete();

            $table->date('departure_date')->index();
            $table->date('arrival_date');
            $table->string('flight_code', 60);           // e.g. "9P700 / 9P701"
            $table->time('departure_time');               // always stored 24h
            $table->time('arrival_time');
            $table->boolean('meal')->default(false);

            $table->unsignedInteger('fare_price');        // PKR, shown on listings
            $table->unsignedInteger('adult_price');
            $table->unsignedInteger('child_price')->default(0);
            $table->unsignedInteger('infant_price')->default(0);

            $table->unsignedInteger('total_seats');
            $table->unsignedInteger('seats_confirmed')->default(0);

            $table->string('pnr', 30)->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft|live|sold_out|closed|departed

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'departure_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
