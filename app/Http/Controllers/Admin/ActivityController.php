<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Flight;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** Admin → Activity Log: who changed what, with old → new values. */
class ActivityController extends Controller
{
    /** log_name => label */
    private const AREAS = [
        'booking' => 'Bookings', 'flight' => 'Flights', 'user' => 'Users', 'agency' => 'Agencies',
        'category' => 'Categories', 'sector' => 'Sectors', 'airline' => 'Airlines', 'baggage_type' => 'Baggage types',
        'role' => 'Roles', 'settings' => 'Settings',
    ];

    /** Money fields never shown to staff without prices.view. */
    private const PRICE_KEYS = ['fare_price', 'adult_price', 'child_price', 'infant_price', 'total_price'];

    public function index(Request $request): Response
    {
        $hidePrices = ! $request->user()->canViewPrices();

        $activities = Activity::query()
            ->with(['causer', 'subject'])
            ->when($request->query('area'), fn ($q, $v) => $q->where('log_name', $v))
            ->when($request->query('event'), fn ($q, $v) => $q->where('event', $v))
            ->when($request->query('user'), fn ($q, $v) => $q->where('causer_id', $v)->where('causer_type', User::class))
            ->when($request->query('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Activity $a) => [
                'id' => $a->id,
                'area' => self::AREAS[$a->log_name] ?? Str::headline((string) $a->log_name),
                'event' => $a->event ?? $a->description,
                'description' => $a->description,
                'subject' => $this->subjectLabel($a),
                'subject_url' => $this->subjectUrl($a),
                'by' => $a->causer?->name ?? 'System',
                'at' => $a->created_at->format('d M Y, h:i A'),
                'ago' => $a->created_at->diffForHumans(),
                'changes' => $this->changes($a, $hidePrices),
            ]);

        return Inertia::render('Admin/Activity/Index', [
            'activities' => $activities,
            'filters' => $request->only('area', 'event', 'user', 'from', 'to'),
            'areas' => collect(self::AREAS)->map(fn ($label, $value) => compact('value', 'label'))->values(),
            'users' => User::whereIn('type', ['super_admin', 'staff'])->orderBy('name')->get(['id', 'name'])
                ->map(fn ($u) => ['value' => $u->id, 'label' => $u->name]),
        ]);
    }

    private function changes(Activity $a, bool $hidePrices): array
    {
        $old = (array) ($a->properties['old'] ?? []);
        $new = (array) ($a->properties['attributes'] ?? []);

        return collect(array_unique([...array_keys($new), ...array_keys($old)]))
            ->reject(fn ($k) => $hidePrices && in_array($k, self::PRICE_KEYS, true))
            ->map(fn ($k) => [
                'field' => Str::headline(str_replace('.', ' ', $k)),
                'old' => $this->fmt($old[$k] ?? null),
                'new' => $this->fmt($new[$k] ?? null),
            ])->values()->all();
    }

    private function fmt(mixed $v): ?string
    {
        return match (true) {
            $v === null || $v === '' => null,
            is_bool($v) => $v ? 'Yes' : 'No',
            is_array($v) => implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x), $v)) ?: '—',
            default => Str::limit((string) $v, 120),
        };
    }

    private function subjectLabel(Activity $a): ?string
    {
        $s = $a->subject;
        if (! $s) {
            return $a->properties['attributes']['name'] ?? $a->properties['old']['name'] ?? null;
        }

        return match (true) {
            $s instanceof Booking => $s->booking_no,
            $s instanceof Flight => $s->flight_code.' · '.$s->departure_date?->format('d M Y'),
            isset($s->agency_name) => $s->agency_name,
            isset($s->label) => $s->label,
            default => $s->name ?? '#'.$s->getKey(),
        };
    }

    private function subjectUrl(Activity $a): ?string
    {
        $s = $a->subject;

        return match (true) {
            $s instanceof Booking => route('admin.bookings.show', $s),
            $s instanceof Flight => route('admin.flights.edit', $s),
            $s instanceof User && $s->isAgent() => route('admin.agents.show', $s),
            default => null,
        };
    }
}
