<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Http\Resources\AgentFlightResource;
use App\Models\Category;
use App\Models\Flight;
use App\Models\Sector;
use Inertia\Inertia;
use Inertia\Response;

class GroupController extends Controller
{
    /** Groups page: main categories with how many bookable flights each has. */
    public function index(): Response
    {
        $counts = Flight::bookable()
            ->selectRaw('category_id, count(*) as total, min(fare_price) as from_price')
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        return Inertia::render('Agent/Groups/Index', [
            'categories' => Category::active()->ordered()->get()
                ->map(fn (Category $c) => [
                    ...$c->only('id', 'name', 'slug', 'country', 'image_url', 'flag_url'),
                    'flights_count' => (int) ($counts[$c->id]->total ?? 0),
                    'from_price' => isset($counts[$c->id]) ? (int) $counts[$c->id]->from_price : null,
                ]),
        ]);
    }

    /** Category page: sector buttons and the bookable flights grouped by sector. */
    public function show(Category $category): Response
    {
        abort_unless($category->is_active, 404);

        $flights = Flight::bookable()
            ->where('category_id', $category->id)
            ->whereHas('sector', fn ($q) => $q->where('is_active', true))
            ->with(['airline:id,name,iata_code,logo', 'baggageType:id,label'])
            ->orderBy('departure_date')
            ->orderBy('departure_time')
            ->get();

        $sectorIds = $flights->pluck('sector_id')->unique();

        return Inertia::render('Agent/Groups/Show', [
            'category' => $category->only('id', 'name', 'slug', 'country', 'description', 'image_url', 'flag_url'),
            // Only sectors that currently have flights, in admin order.
            'sectors' => Sector::whereIn('id', $sectorIds)->ordered()->get(['id', 'name', 'origin', 'destination', 'trip_type'])
                ->map(fn ($s) => [...$s->only('id', 'name', 'origin', 'destination'), 'trip_type' => $s->trip_type->value]),
            'flights' => AgentFlightResource::collection($flights)->resolve(),
        ]);
    }
}
