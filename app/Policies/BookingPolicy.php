<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

/** Agent-side ownership rules. Admin actions are gated by route permissions. */
class BookingPolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $user->isAdminSide() ? $user->can('bookings.view') : $booking->agent_id === $user->id;
    }

    /** Agents edit only their own pending requests; admins any open or confirmed booking. */
    public function update(User $user, Booking $booking): bool
    {
        if ($user->isAdminSide()) {
            return $user->can('bookings.edit') && ! in_array($booking->status, [BookingStatus::Rejected, BookingStatus::Cancelled], true);
        }

        return $booking->agent_id === $user->id && $booking->status === BookingStatus::Pending;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        return $booking->agent_id === $user->id && $booking->status === BookingStatus::Pending;
    }

    public function export(User $user, Booking $booking): bool
    {
        return $user->isAdminSide() ? $user->can('bookings.export') : $booking->agent_id === $user->id;
    }
}
