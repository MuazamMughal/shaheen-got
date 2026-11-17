<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Barryvdh\DomPDF\Facade\Pdf;

/** Builds the booking PDF. Used by the download buttons and the confirmation email. */
class BookingVoucher
{
    public function __construct(private readonly SettingsService $settings) {}

    public function render(Booking $booking, bool $showPrices, bool $showPnr): string
    {
        $booking->loadMissing(['passengers', 'agent.agency', 'flight' => fn ($q) => $q->with(['sector', 'category', 'airline', 'baggageType'])]);

        $logo = $this->settings->get('site.logo')
            ? storage_path('app/public/'.$this->settings->get('site.logo'))
            : public_path('images/logo.png');

        return Pdf::loadView('pdf.booking', [
            'booking' => $booking,
            'showPrices' => $showPrices,
            'showPnr' => $showPnr,
            'site' => $this->settings->publicSettings(),
            'theme' => $this->settings->theme(),
            'logo' => is_file($logo) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) : null,
        ])->setPaper('a4')->setOption('isFontSubsettingEnabled', true)->output();
    }

    /** PNR is shown to admins always, to everyone else once confirmed. */
    public static function pnrVisible(Booking $booking, bool $isAdmin): bool
    {
        return $isAdmin || $booking->status === BookingStatus::Confirmed;
    }
}
