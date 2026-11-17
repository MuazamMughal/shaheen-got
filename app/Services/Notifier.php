<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use App\Notifications\AgentNotification;
use App\Notifications\BaseNotification;
use App\Notifications\BookingNotification;
use App\Notifications\FlightSeatsNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Decides WHO hears about each event (docs/ARCHITECTURE.md §6).
 *
 * "Admins" = active super admins + active staff holding the relevant
 * permission + the extra addresses in Admin → Settings → Notifications.
 * Agent emails also go to the agency's extra notification emails.
 */
class Notifier
{
    public function __construct(private readonly SettingsService $settings) {}

    // ---- Agents -------------------------------------------------------

    public function agentRegistered(User $agent): void
    {
        $this->toAdmins(new AgentNotification($agent, 'registered', 'admin'), 'agents.approve');
        $this->toAgent($agent, new AgentNotification($agent, 'registered', 'agent'));
    }

    public function agentApproved(User $agent): void
    {
        $this->toAgent($agent, new AgentNotification($agent, 'approved', 'agent'));
    }

    public function agentRejected(User $agent): void
    {
        $this->toAgent($agent, new AgentNotification($agent, 'rejected', 'agent'));
    }

    // ---- Bookings -----------------------------------------------------

    public function bookingCreated(Booking $booking): void
    {
        $this->toAdmins(new BookingNotification($booking, 'created', 'admin'), 'bookings.view');
        $this->toAgent($booking->agent, new BookingNotification($booking, 'created', 'agent'));
        $this->toBooker($booking, new BookingNotification($booking, 'created', 'booker'));
    }

    public function paymentApproved(Booking $booking): void
    {
        $this->toAgent($booking->agent, new BookingNotification($booking, 'payment_approved', 'agent'));
    }

    public function bookingConfirmed(Booking $booking): void
    {
        $this->toAgent($booking->agent, new BookingNotification($booking, 'confirmed', 'agent'));
        $this->toBooker($booking, new BookingNotification($booking, 'confirmed', 'booker'));
    }

    public function bookingRejected(Booking $booking): void
    {
        $this->toAgent($booking->agent, new BookingNotification($booking, 'rejected', 'agent'));
        $this->toBooker($booking, new BookingNotification($booking, 'rejected', 'booker'));
    }

    public function bookingCancelled(Booking $booking): void
    {
        $this->toAdmins(new BookingNotification($booking, 'cancelled', 'admin'), 'bookings.view');
        $this->toAgent($booking->agent, new BookingNotification($booking, 'cancelled', 'agent'));
    }

    /** After seats are confirmed: alert on sold out, or when crossing the low-seat threshold. */
    public function seatsChanged(Flight $flight, int $previousAvailable): void
    {
        $now = $flight->seats_available;
        $threshold = $this->settings->lowSeatThreshold();

        if ($now === 0 && $previousAvailable > 0) {
            $this->toAdmins(new FlightSeatsNotification($flight, 'sold_out'), 'bookings.view');
        } elseif ($threshold > 0 && $now <= $threshold && $previousAvailable > $threshold) {
            $this->toAdmins(new FlightSeatsNotification($flight, 'low'), 'bookings.view');
        }
    }

    // ---- Recipients ---------------------------------------------------

    /** @return Collection<int, User> */
    public function adminUsers(string $permission): Collection
    {
        $supers = User::ofType(UserType::SuperAdmin)->where('status', UserStatus::Approved)->get();
        try {
            $staff = User::ofType(UserType::Staff)->where('status', UserStatus::Approved)->permission($permission)->get();
        } catch (PermissionDoesNotExist) {
            $staff = collect(); // permissions not seeded yet — only super admins
        }

        return $supers->merge($staff)->unique('id')->values();
    }

    private function toAdmins(BaseNotification $notification, string $permission): void
    {
        $users = $this->adminUsers($permission);
        Notification::send($users, $notification);

        $known = $users->pluck('email')->map(fn ($e) => mb_strtolower($e))->all();
        $extra = array_values(array_diff($this->settings->adminEmails(), $known));
        if ($extra) {
            Notification::route('mail', $extra)->notify($notification);
        }
    }

    private function toAgent(?User $agent, BaseNotification $notification): void
    {
        if (! $agent) {
            return;
        }

        $agent->notify($notification);

        $extra = array_values(array_diff($agent->agency?->notification_emails ?? [], [mb_strtolower($agent->email)]));
        if ($extra) {
            Notification::route('mail', $extra)->notify($notification);
        }
    }

    private function toBooker(Booking $booking, BaseNotification $notification): void
    {
        $email = mb_strtolower((string) $booking->booker_email);
        if ($email === '') {
            return;
        }

        // Don't email the agent twice when they entered their own address as booker.
        $agentEmails = array_map('mb_strtolower', $booking->agent?->agency?->allNotificationEmails() ?? [$booking->agent?->email]);
        if (in_array($email, $agentEmails, true)) {
            return;
        }

        Notification::route('mail', $email)->notify($notification);
    }
}
