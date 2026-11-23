<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\FlightStatus;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every booking status change goes through here so the seat rules and the
 * status log are always applied, and the right people are notified.
 *
 * Rules (docs/ARCHITECTURE.md §5): no seat holding, no expiry, no partial
 * confirmation. Pending requests compete; only CONFIRM uses seats, and it
 * re-checks availability under a row lock so seats can never be oversold.
 */
class BookingService
{
    public function __construct(private readonly Notifier $notifier) {}

    public function create(User $agent, Flight $flight, array $data, array $passengers): Booking
    {
        return DB::transaction(function () use ($agent, $flight, $data, $passengers) {
            $flight = Flight::whereKey($flight->id)->lockForUpdate()->firstOrFail();

            if ($flight->status !== FlightStatus::Live || $flight->departure_date->isBefore(today())) {
                throw ValidationException::withMessages(['flight' => 'This group is no longer available for booking.']);
            }
            $this->ensureSeats($flight, $data['adults'] + ($data['children'] ?? 0));

            $booking = new Booking($data);
            $booking->forceFill([
                'agent_id' => $agent->id,
                'flight_id' => $flight->id,
                'adult_price' => $flight->adult_price,
                'child_price' => $flight->child_price,
                'infant_price' => $flight->infant_price,
                'total_price' => Booking::calculateTotal($data['adults'], $data['children'] ?? 0, $data['infants'] ?? 0, $flight->adult_price, $flight->child_price, $flight->infant_price),
                'status' => BookingStatus::Pending,
                'payment_status' => 'unpaid',
            ])->save();

            $booking->passengers()->createMany($passengers);
            $this->log($booking, null, BookingStatus::Pending, $agent, 'Booking requested');

            $this->notifier->bookingCreated($booking->load('agent.agency', 'flight'));

            return $booking;
        });
    }

    /**
     * Edit booker + passengers. Counts (and so price/seats) may change only
     * while the booking is still pending.
     */
    public function update(Booking $booking, array $data, array $passengers, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $data, $passengers, $actor) {
            // Re-read under a lock: the status may have changed since the request was authorized.
            $booking = $this->lock($booking);

            if (in_array($booking->status, [BookingStatus::Rejected, BookingStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['status' => 'This booking is closed and can no longer be edited.']);
            }
            if ($actor->isAgent() && $booking->status !== BookingStatus::Pending) {
                throw ValidationException::withMessages(['status' => 'This booking can no longer be edited. Contact us on WhatsApp.']);
            }

            $countsChanged = (int) $data['adults'] !== $booking->adults
                || (int) ($data['children'] ?? 0) !== $booking->children
                || (int) ($data['infants'] ?? 0) !== $booking->infants;

            if ($countsChanged) {
                if ($booking->status !== BookingStatus::Pending) {
                    throw ValidationException::withMessages(['adults' => 'Passenger numbers can only change while the booking is pending.']);
                }
                $flight = Flight::whereKey($booking->flight_id)->lockForUpdate()->firstOrFail();
                $this->ensureSeats($flight, $data['adults'] + ($data['children'] ?? 0));
            }

            $booking->fill($data);
            $booking->total_price = Booking::calculateTotal(
                $booking->adults, $booking->children, $booking->infants,
                $booking->adult_price, $booking->child_price, $booking->infant_price,
            );
            $booking->save();

            $booking->passengers()->delete();
            $booking->passengers()->createMany($passengers);
            $this->log($booking, $booking->status, $booking->status, $actor, 'Booking details edited');

            return $booking;
        });
    }

    public function approvePayment(Booking $booking, User $admin, ?string $note = null): void
    {
        DB::transaction(function () use ($booking, $admin, $note) {
            // Locked re-read so a booking cancelled at the same moment can't be revived.
            $booking = $this->lock($booking);
            $this->expect($booking, [BookingStatus::Pending], 'Only pending bookings can have their payment approved.');

            $booking->forceFill([
                'status' => BookingStatus::PaymentApproved,
                'payment_status' => 'approved',
                'payment_approved_by' => $admin->id,
                'payment_approved_at' => now(),
                'payment_note' => $note,
            ])->save();

            $this->log($booking, BookingStatus::Pending, BookingStatus::PaymentApproved, $admin, $note ? "Payment approved — {$note}" : 'Payment approved');
            $this->notifier->paymentApproved($booking);
        });
    }

    public function confirm(Booking $booking, User $admin, ?string $remarks = null): void
    {
        DB::transaction(function () use ($booking, $admin, $remarks) {
            $booking = $this->lock($booking);
            $this->expect($booking, [BookingStatus::PaymentApproved], 'Approve the payment before confirming this booking.');

            $flight = Flight::whereKey($booking->flight_id)->lockForUpdate()->firstOrFail();
            $this->ensureSeats($flight, $booking->seatsNeeded(), 'Not enough seats left to confirm this booking');

            $before = $flight->seats_available;
            $flight->seats_confirmed += $booking->seatsNeeded();
            $flight->save(); // becomes sold_out automatically when full

            $booking->forceFill([
                'status' => BookingStatus::Confirmed,
                'confirmed_by' => $admin->id,
                'confirmed_at' => now(),
                'admin_remarks' => $remarks ?? $booking->admin_remarks,
            ])->save();

            $this->log($booking, BookingStatus::PaymentApproved, BookingStatus::Confirmed, $admin, 'Booking confirmed');

            $this->notifier->bookingConfirmed($booking);
            $this->notifier->seatsChanged($flight, $before);
        });
    }

    public function reject(Booking $booking, User $admin, string $reason): void
    {
        DB::transaction(function () use ($booking, $admin, $reason) {
            // Locked re-read so a booking confirmed at the same moment isn't rejected with its seats still counted.
            $booking = $this->lock($booking);
            $from = $booking->status;
            $this->expect($booking, BookingStatus::open(), 'Only pending or payment-approved bookings can be rejected.');

            $booking->forceFill([
                'status' => BookingStatus::Rejected,
                'rejected_by' => $admin->id,
                'rejected_at' => now(),
                'reject_reason' => $reason,
            ])->save();

            $this->log($booking, $from, BookingStatus::Rejected, $admin, $reason);
            $this->notifier->bookingRejected($booking);
        });
    }

    /** Agents may cancel their own pending requests; admins may also cancel confirmed ones (seats return). */
    public function cancel(Booking $booking, User $actor, ?string $reason = null): void
    {
        DB::transaction(function () use ($booking, $actor, $reason) {
            $booking = $this->lock($booking);
            $from = $booking->status;

            $allowed = $actor->isAgent() ? [BookingStatus::Pending] : [...BookingStatus::open(), BookingStatus::Confirmed];
            $this->expect($booking, $allowed, $actor->isAgent()
                ? 'You can only cancel a booking before its payment is approved. Contact us on WhatsApp.'
                : 'This booking is already closed.');

            if ($from === BookingStatus::Confirmed) {
                $flight = Flight::whereKey($booking->flight_id)->lockForUpdate()->firstOrFail();
                $flight->seats_confirmed = max(0, $flight->seats_confirmed - $booking->seatsNeeded());
                $flight->save(); // sold_out → live again if seats free up
            }

            $booking->forceFill([
                'status' => BookingStatus::Cancelled,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();

            $this->log($booking, $from, BookingStatus::Cancelled, $actor, $reason ?? 'Booking cancelled');
            $this->notifier->bookingCancelled($booking);
        });
    }

    /** Fresh copy of the booking, row-locked until the surrounding transaction ends. */
    private function lock(Booking $booking): Booking
    {
        return Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
    }

    private function ensureSeats(Flight $flight, int $seats, string $prefix = 'Only'): void
    {
        if ($seats > $flight->seats_available) {
            $msg = $prefix === 'Only'
                ? "Only {$flight->seats_available} seat(s) left on this group."
                : "{$prefix}: {$flight->seats_available} available, {$seats} needed.";

            throw ValidationException::withMessages(['seats' => $msg]);
        }
    }

    /** @param list<BookingStatus> $allowed */
    private function expect(Booking $booking, array $allowed, string $message): void
    {
        if (! in_array($booking->status, $allowed, true)) {
            throw ValidationException::withMessages(['status' => $message]);
        }
    }

    private function log(Booking $booking, ?BookingStatus $from, BookingStatus $to, ?User $user, ?string $note): void
    {
        $booking->logs()->create([
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'user_id' => $user?->id,
            'note' => $note,
        ]);
    }
}
