<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('agency_name', 160);
            $table->string('owner_name', 120);
            $table->string('city', 80);
            $table->string('address', 500)->nullable();
            $table->string('phone', 30);
            $table->string('whatsapp', 20)->nullable();
            $table->string('logo')->nullable();
            $table->json('notification_emails')->nullable(); // extra addresses for booking mails
            $table->timestamps();
        });

        // Why an agent was rejected or suspended (shown to them and to admins).
        Schema::table('users', function (Blueprint $table) {
            $table->string('status_reason', 500)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('status_reason'));
        Schema::dropIfExists('agencies');
    }
};
