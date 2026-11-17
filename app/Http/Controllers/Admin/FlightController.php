<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlightStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FlightRequest;
use App\Http\Resources\FlightResource;
use App\Models\Airline;
use App\Models\BaggageType;
use App\Models\Category;
use App\Models\Flight;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FlightController extends Controller
{
    private const SORTABLE = ['departure_date', 'flight_code', 'total_seats', 'status', 'created_at'];

    public function index(Request $request): Response
    {
        $filters = $request->only('category', 'sector', 'airline', 'status', 'from', 'to', 'search', 'sort', 'direction', 'past');
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'departure_date';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $flights = Flight::query()
            ->with(['category:id,name', 'sector:id,name,trip_type', 'airline:id,name,iata_code,logo', 'baggageType:id,label'])
            ->withPendingSeats()
            ->when($filters['category'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['sector'] ?? null, fn ($q, $v) => $q->where('sector_id', $v))
            ->when($filters['airline'] ?? null, fn ($q, $v) => $q->where('airline_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('departure_date', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('departure_date', '<=', $v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($q) => $q
                ->where('flight_code', 'like', "%{$v}%")->orWhere('pnr', 'like', "%{$v}%")))
            // Past flights are hidden by default to keep the list focused.
            ->when(! ($filters['past'] ?? false) && empty($filters['from']), fn ($q) => $q->upcoming())
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Flights/Index', [
            'flights' => FlightResource::collection($flights),
            'filters' => $filters,
            'options' => $this->filterOptions(),
            'counts' => [
                'live' => Flight::where('status', FlightStatus::Live)->upcoming()->count(),
                'draft' => Flight::where('status', FlightStatus::Draft)->upcoming()->count(),
                'sold_out' => Flight::where('status', FlightStatus::SoldOut)->upcoming()->count(),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->canViewPrices(), 403, 'Creating flights requires permission to view prices.');

        return $this->form(null);
    }

    /** New form pre-filled from an existing flight; the admin changes the dates. */
    public function duplicate(Request $request, Flight $flight): Response
    {
        abort_unless($request->user()->canViewPrices(), 403, 'Creating flights requires permission to view prices.');

        $copy = (new FlightResource($flight))->resolve($request);
        $copy = [...$copy, 'id' => null, 'departure_date' => '', 'arrival_date' => '', 'pnr' => '', 'seats_confirmed' => 0];

        return $this->form($copy, $flight);
    }

    public function store(FlightRequest $request): RedirectResponse
    {
        $publish = $request->boolean('publish');
        abort_if($publish && ! $request->user()->can('flights.publish'), 403);

        $flight = new Flight($request->safe()->except('publish'));
        $flight->forceFill([
            'status' => $publish ? FlightStatus::Live : FlightStatus::Draft,
            'created_by' => $request->user()->id,
        ])->save();

        return redirect()->route('admin.flights.index')
            ->with('success', $publish ? 'Flight created and published.' : 'Flight saved as draft.');
    }

    public function edit(Flight $flight): Response
    {
        return $this->form((new FlightResource($flight->load('category', 'sector', 'airline', 'baggageType')))->resolve(request()), $flight);
    }

    public function update(FlightRequest $request, Flight $flight): RedirectResponse
    {
        $data = $request->safe()->except('publish');
        if (! $request->user()->canViewPrices()) {
            $data = array_diff_key($data, array_flip(Flight::PRICE_FIELDS));
        }

        $flight->fill($data)->save();

        return redirect()->route('admin.flights.index')->with('success', 'Flight updated.');
    }

    /** Publish (draft/closed → live) or unpublish (live/sold out → closed). */
    public function togglePublish(Flight $flight): RedirectResponse
    {
        if ($flight->status === FlightStatus::Departed) {
            return back()->with('error', 'Departed flights cannot be published.');
        }

        if ($flight->status->isPublished()) {
            $flight->forceFill(['status' => FlightStatus::Closed])->save();

            return back()->with('success', 'Flight unpublished. Agents can no longer see it.');
        }

        if ($flight->departure_date->isBefore(today())) {
            return back()->with('error', 'This flight has already departed and cannot be published.');
        }

        $flight->forceFill(['status' => FlightStatus::Live])->save(); // becomes sold_out automatically if full

        return back()->with('success', 'Flight published. Agents can now book it.');
    }

    public function destroy(Flight $flight): RedirectResponse
    {
        if ($reason = $flight->deleteBlocker()) {
            return back()->with('error', $reason);
        }

        $flight->delete();

        return back()->with('success', 'Flight deleted.');
    }

    private function form(?array $flight, ?Flight $source = null): Response
    {
        // Active options, plus the source flight's current (maybe inactive) choices.
        $include = fn (string $field) => $source?->{$field};

        return Inertia::render('Admin/Flights/Form', [
            'flight' => $flight,
            'isDuplicate' => $flight !== null && $flight['id'] === null,
            'options' => [
                'categories' => Category::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $include('category_id')))
                    ->ordered()->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
                'sectors' => Sector::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $include('sector_id')))
                    ->ordered()->get(['id', 'name', 'category_id', 'trip_type'])
                    ->map(fn ($s) => ['value' => $s->id, 'label' => $s->name, 'category_id' => $s->category_id, 'trip_type' => $s->trip_type->value]),
                'airlines' => Airline::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $include('airline_id')))
                    ->orderBy('name')->get(['id', 'name', 'iata_code'])
                    ->map(fn ($a) => ['value' => $a->id, 'label' => $a->name.($a->iata_code ? " ({$a->iata_code})" : ''), 'iata' => $a->iata_code]),
                'baggageTypes' => BaggageType::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $include('baggage_type_id')))
                    ->ordered()->get(['id', 'label'])->map(fn ($b) => ['value' => $b->id, 'label' => $b->label]),
            ],
        ]);
    }

    private function filterOptions(): array
    {
        return [
            'categories' => Category::ordered()->get(['id', 'name'])->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'sectors' => Sector::ordered()->get(['id', 'name', 'category_id'])->map(fn ($s) => ['value' => $s->id, 'label' => $s->name, 'category_id' => $s->category_id]),
            'airlines' => Airline::orderBy('name')->get(['id', 'name'])->map(fn ($a) => ['value' => $a->id, 'label' => $a->name]),
            'statuses' => FlightStatus::options(),
        ];
    }
}
