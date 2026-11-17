<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\BookingVoucher;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Booking events: created | payment_approved | confirmed | rejected | cancelled.
 * The confirmed email carries the PDF voucher (without prices for bookers).
 */
class BookingNotification extends BaseNotification
{
    public function __construct(public Booking $booking, public string $event, string $audience)
    {
        parent::__construct($audience);
    }

    protected function title(): string
    {
        $no = $this->booking->booking_no;
        $agency = $this->booking->agent?->agency?->agency_name ?? $this->booking->agent?->name;

        return match ([$this->event, $this->audience]) {
            ['created', 'admin'] => "New booking {$no} from {$agency}",
            ['created', 'agent'] => "Booking {$no} received — awaiting payment",
            ['created', 'booker'] => "Your booking request {$no} has been received",
            ['payment_approved', 'agent'] => "Payment approved for {$no}",
            ['confirmed', 'agent'], ['confirmed', 'booker'] => "Booking {$no} is confirmed",
            ['rejected', 'agent'], ['rejected', 'booker'] => "Booking {$no} could not be confirmed",
            ['cancelled', 'admin'] => "Booking {$no} was cancelled",
            ['cancelled', 'agent'] => "Booking {$no} has been cancelled",
            default => "Booking {$no} updated",
        };
    }

    protected function body(): string
    {
        $b = $this->booking;
        $f = $b->flight;
        $trip = ($f->sector?->name ?? 'your group').' on '.$f->departure_date->format('d M Y').' ('.$f->flight_code.')';

        return match ([$this->event, $this->audience]) {
            ['created', 'admin'] => "{$b->totalPax()} passenger(s) requested on {$trip}. Approve the payment once received, then confirm the seats.",
            ['created', 'agent'] => "We received your booking for {$trip}. Send your payment proof to our team on WhatsApp so we can approve the payment and confirm your seats.",
            ['created', 'booker'] => "Your travel agent has requested seats on {$trip}. You will receive another email once the booking is confirmed.",
            ['payment_approved', 'agent'] => "Your payment for {$trip} has been received and approved. Our team is now confirming your seats.",
            ['confirmed', 'agent'] => "Your seats on {$trip} are confirmed. PNR: ".($f->pnr ?: 'will be shared shortly').'. Your booking voucher is attached.',
            ['confirmed', 'booker'] => "Your seats on {$trip} are confirmed. PNR: ".($f->pnr ?: 'will be shared shortly').'. Your booking voucher is attached.',
            ['rejected', 'agent'], ['rejected', 'booker'] => "We could not confirm the booking for {$trip}.".($b->reject_reason ? " Reason: {$b->reject_reason}" : ''),
            ['cancelled', 'admin'] => "{$b->booking_no} on {$trip} was cancelled.".($b->cancel_reason ? " Reason: {$b->cancel_reason}" : '').($b->confirmed_at ? ' Its seats are back on sale.' : ''),
            ['cancelled', 'agent'] => "Booking {$b->booking_no} for {$trip} has been cancelled.".($b->cancel_reason ? " Reason: {$b->cancel_reason}" : ''),
            default => '',
        };
    }

    protected function extendMail(MailMessage $mail, object $notifiable): void
    {
        $b = $this->booking;
        $f = $b->flight;
        $showPrices = $this->showPrices($notifiable);

        $rows = [
            '| | |',
            '|:--|:--|',
            "| Booking | **{$b->booking_no}** |",
            '| Sector | '.($f->sector?->name ?? '').' |',
            '| Airline | '.($f->airline?->name ?? '').' |',
            "| Flight | {$f->flight_code} |",
            '| Departure | '.$f->departure_date->format('d M Y').' · '.substr((string) $f->departure_time, 0, 5).' |',
            '| Return | '.$f->arrival_date->format('d M Y').' · '.substr((string) $f->arrival_time, 0, 5).' |',
            '| Passengers | '.$b->totalPax().' (lead: '.($b->leadPassenger?->fullName() ?? $b->booker_name).') |',
        ];
        if ($this->audience === 'admin') {
            $rows[] = '| Agency | '.($b->agent?->agency?->agency_name ?? $b->agent?->name).' |';
        }
        if ($this->event === 'confirmed') {
            $rows[] = '| PNR | **'.($f->pnr ?: '—').'** |';
        }
        if ($showPrices) {
            $rows[] = '| Total | **PKR '.number_format($b->total_price).'** |';
        }
        $mail->line(Markdown::parse(implode("\n", $rows)));

        if ($this->event === 'confirmed') {
            $pdf = app(BookingVoucher::class)->render($b, showPrices: $showPrices, showPnr: true);
            $mail->attachData($pdf, "{$b->booking_no}.pdf", ['mime' => 'application/pdf']);
        }
    }

    protected function url(): ?string
    {
        return match ($this->audience) {
            'admin' => route('admin.bookings.show', $this->booking),
            'agent' => route('agent.bookings.show', $this->booking),
            default => null,
        };
    }

    protected function actionText(): string
    {
        return match ([$this->event, $this->audience]) {
            ['created', 'admin'] => 'Review booking',
            default => 'View booking',
        };
    }

    protected function tone(): string
    {
        return match ($this->event) {
            'created' => 'amber',
            'payment_approved' => 'blue',
            'confirmed' => 'green',
            'rejected', 'cancelled' => 'red',
            default => 'navy',
        };
    }
}
