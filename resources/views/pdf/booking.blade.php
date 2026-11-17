@php
    $f = $booking->flight;
    $navy = $theme['secondary'];
    $gold = $theme['tertiary'];
    $statusColors = ['pending' => '#B45309', 'payment_approved' => '#0369A1', 'confirmed' => '#047857', 'rejected' => '#BE123C', 'cancelled' => '#475569'];
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $booking->booking_no }}</title>
<style>
    @page { margin: 28px 32px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1e293b; }
    .header { background: {{ $navy }}; color: #fff; padding: 16px 18px; border-radius: 8px; }
    .header td { vertical-align: middle; }
    .brand { color: {{ $gold }}; font-size: 18px; font-weight: bold; letter-spacing: 3px; }
    .muted { color: #64748b; }
    .small { font-size: 9px; }
    h2 { font-size: 13px; color: {{ $navy }}; margin: 18px 0 6px; border-bottom: 2px solid {{ $gold }}; padding-bottom: 4px; }
    table { width: 100%; border-collapse: collapse; }
    .grid td { padding: 5px 6px; border-bottom: 1px solid #e2e8f0; }
    .grid td.label { color: #64748b; width: 22%; }
    .pax th { background: {{ $navy }}; color: #fff; font-size: 9px; text-transform: uppercase; padding: 6px; text-align: left; }
    .pax td { padding: 6px; border-bottom: 1px solid #e2e8f0; }
    .pax tr:nth-child(even) td { background: #faf6ec; }
    .badge { display: inline-block; padding: 3px 9px; border-radius: 10px; color: #fff; font-weight: bold; font-size: 10px; }
    .total td { padding: 6px; }
    .total .grand td { background: #faf6ec; font-weight: bold; font-size: 13px; color: {{ $navy }}; border-top: 2px solid {{ $gold }}; }
    .footer { margin-top: 26px; font-size: 9px; color: #64748b; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 8px; }
</style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 60px;">@if($logo)<img src="{{ $logo }}" style="width: 52px; height: 52px; border-radius: 6px;">@endif</td>
            <td>
                <div class="brand">SHAHEEN</div>
                <div style="font-size: 9px; letter-spacing: 2px; color: #cbd5e1;">GROUP OF TRAVELS</div>
            </td>
            <td style="text-align: right;">
                <div style="font-size: 9px; color: #cbd5e1;">BOOKING</div>
                <div style="font-size: 16px; font-weight: bold;">{{ $booking->booking_no }}</div>
                <span class="badge" style="background: {{ $statusColors[$booking->status->value] ?? '#475569' }};">{{ strtoupper($booking->status->label()) }}</span>
            </td>
        </tr>
    </table>

    <h2>Flight</h2>
    <table class="grid">
        <tr><td class="label">Sector</td><td><strong>{{ $f->sector?->name }}</strong> ({{ $f->category?->name }})</td><td class="label">Airline</td><td>{{ $f->airline?->name }}</td></tr>
        <tr><td class="label">Flight</td><td>{{ $f->flight_code }}</td><td class="label">PNR</td><td><strong>{{ $showPnr ? ($f->pnr ?: '—') : 'Shown after confirmation' }}</strong></td></tr>
        <tr><td class="label">Departure</td><td>{{ $f->departure_date->format('d M Y') }} · {{ substr($f->departure_time, 0, 5) }}</td><td class="label">Arrival / Return</td><td>{{ $f->arrival_date->format('d M Y') }} · {{ substr($f->arrival_time, 0, 5) }}</td></tr>
        <tr><td class="label">Baggage</td><td>{{ $f->baggageType?->label }}</td><td class="label">Meal</td><td>{{ $f->meal ? 'Yes' : 'No' }}</td></tr>
    </table>

    <h2>Booking</h2>
    <table class="grid">
        <tr><td class="label">Agency</td><td>{{ $booking->agent?->agency?->agency_name ?? $booking->agent?->name }}</td><td class="label">Booked on</td><td>{{ $booking->created_at->format('d M Y, h:i A') }}</td></tr>
        <tr><td class="label">Booker</td><td>{{ $booking->booker_name }}</td><td class="label">Phone</td><td>{{ $booking->booker_phone }}</td></tr>
        <tr><td class="label">Passengers</td><td colspan="3">{{ $booking->adults }} adult(s){{ $booking->children ? ", {$booking->children} child(ren)" : '' }}{{ $booking->infants ? ", {$booking->infants} infant(s)" : '' }}</td></tr>
    </table>

    <h2>Passengers</h2>
    <table class="pax">
        <thead><tr><th>#</th><th>Type</th><th>Name</th><th>Passport</th><th>Date of birth</th><th>Passport expiry</th></tr></thead>
        <tbody>
        @foreach ($booking->passengers as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $p->pax_type->label() }}{{ $p->is_lead ? ' (Lead)' : '' }}</td>
                <td><strong>{{ $p->title }} {{ $p->given_name }} {{ $p->surname }}</strong></td>
                <td>{{ $p->passport_no }}</td>
                <td>{{ $p->dob->format('d-m-Y') }}</td>
                <td>{{ $p->passport_expiry_date->format('d-m-Y') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    @if ($showPrices)
        <h2>Fare</h2>
        <table class="total">
            <tr><td>Adult × {{ $booking->adults }}</td><td style="text-align:right;">PKR {{ number_format($booking->adult_price * $booking->adults) }}</td></tr>
            @if ($booking->children)<tr><td>Child × {{ $booking->children }}</td><td style="text-align:right;">PKR {{ number_format($booking->child_price * $booking->children) }}</td></tr>@endif
            @if ($booking->infants)<tr><td>Infant × {{ $booking->infants }}</td><td style="text-align:right;">PKR {{ number_format($booking->infant_price * $booking->infants) }}</td></tr>@endif
            <tr class="grand"><td>Total</td><td style="text-align:right;">PKR {{ number_format($booking->total_price) }}</td></tr>
        </table>
    @endif

    <div class="footer">
        {{ $site['site']['name'] }}
        @if ($site['contact']['phone']) · {{ $site['contact']['phone'] }} @endif
        @if ($site['contact']['whatsapp']) · WhatsApp {{ $site['contact']['whatsapp'] }} @endif
        @if ($site['contact']['email']) · {{ $site['contact']['email'] }} @endif
        <br>Generated {{ now()->format('d M Y, h:i A') }}
    </div>
</body>
</html>
