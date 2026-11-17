<?php

namespace App\Notifications;

use App\Models\Flight;

/** To admins: a flight sold out, or dropped to the low-seat threshold. */
class FlightSeatsNotification extends BaseNotification
{
    public function __construct(public Flight $flight, public string $event)
    {
        parent::__construct('admin');
    }

    protected function title(): string
    {
        $name = ($this->flight->sector?->name ?? 'Flight').' '.$this->flight->departure_date->format('d M');

        return $this->event === 'sold_out'
            ? "Sold out: {$name}"
            : "Only {$this->flight->seats_available} seat(s) left: {$name}";
    }

    protected function body(): string
    {
        $f = $this->flight;
        $what = "{$f->flight_code} ({$f->airline?->name}) departing {$f->departure_date->format('d M Y')}";

        return $this->event === 'sold_out'
            ? "All {$f->total_seats} seats on {$what} are confirmed. It is no longer shown to agents. Reject any remaining open requests or add seats."
            : "{$what} has {$f->seats_available} of {$f->total_seats} seats left.";
    }

    protected function url(): ?string
    {
        return route('admin.flights.bookings', $this->flight);
    }

    protected function actionText(): string
    {
        return 'Open flight bookings';
    }

    protected function tone(): string
    {
        return $this->event === 'sold_out' ? 'red' : 'amber';
    }
}
