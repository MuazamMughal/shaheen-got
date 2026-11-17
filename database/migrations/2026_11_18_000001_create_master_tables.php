<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('country', 120)->nullable();
            $table->string('flag_icon')->nullable();   // storage path (public disk)
            $table->string('image')->nullable();       // storage path (public disk)
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('sectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 160);                 // e.g. MULTAN-JEDDAH-MULTAN
            $table->string('origin', 80);
            $table->string('destination', 80);
            $table->string('trip_type', 20)->default('return'); // one_way | return
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['category_id', 'name']);
        });

        Schema::create('airlines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('iata_code', 3)->nullable()->unique();
            $table->string('logo')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('baggage_types', function (Blueprint $table) {
            $table->id();
            $table->string('label', 120)->unique();      // e.g. 20+10 KG, 1PC X 23 KG
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baggage_types');
        Schema::dropIfExists('airlines');
        Schema::dropIfExists('sectors');
        Schema::dropIfExists('categories');
    }
};
