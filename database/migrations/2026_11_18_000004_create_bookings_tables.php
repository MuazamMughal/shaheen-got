<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_no', 20)->nullable()->unique();      // BK-000123
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('flight_id')->constrained()->restrictOnDelete();

            $table->string('booker_name', 120);
            $table->string('booker_phone', 30);
            $table->string('booker_email')->nullable();

            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children')->default(0);
            $table->unsignedTinyInteger('infants')->default(0);

            // Price snapshot at booking time — later flight price edits don't change it.
            $table->unsignedInteger('adult_price');
            $table->unsignedInteger('child_price');
            $table->unsignedInteger('infant_price');
            $table->unsignedInteger('total_price');

            $table->string('status', 20)->default('pending')->index();      // pending|payment_approved|confirmed|rejected|cancelled
            $table->string('payment_status', 20)->default('unpaid')->index(); // unpaid|approved

            $table->foreignId('payment_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_approved_at')->nullable();
            $table->string('payment_note', 255)->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->string('reject_reason', 500)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();

            $table->text('admin_remarks')->nullable();
            $table->text('agent_remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['flight_id', 'status']);
            $table->index(['agent_id', 'status']);
        });

        Schema::create('booking_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('pax_type', 10);            // adult|child|infant
            $table->string('title', 10);
            $table->string('given_name', 80);
            $table->string('surname', 80);
            $table->string('passport_no', 30);
            $table->date('dob');
            $table->date('passport_issue_date')->nullable();
            $table->date('passport_expiry_date');
            $table->boolean('is_lead')->default(false);
            $table->unsignedTinyInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('booking_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_status_logs');
        Schema::dropIfExists('booking_passengers');
        Schema::dropIfExists('bookings');
    }
};
