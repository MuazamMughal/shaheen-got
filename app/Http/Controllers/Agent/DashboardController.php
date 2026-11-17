<?php

namespace App\Http\Controllers\Agent;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Flight;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $mine = Booking::where('agent_id', $request->user()->id);
        $byStatus = (clone $mine)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $counts = Flight::bookable()->selectRaw('category_id, count(*) as total')->groupBy('category_id')->pluck('total', 'category_id');

        // Two busiest categories become quick links.
        $featured = Category::active()->whereIn('id', $counts->keys())->ordered()->get()
            ->sortByDesc(fn ($c) => $counts[$c->id])->take(2)->values()
            ->map(fn ($c) => [...$c->only('id', 'name', 'slug', 'country', 'image_url'), 'flights_count' => (int) $counts[$c->id]]);

        return Inertia::render('Agent/Dashboard', [
            'stats' => [
                'confirmed' => (int) ($byStatus[BookingStatus::Confirmed->value] ?? 0),
                'pending' => (int) (($byStatus[BookingStatus::Pending->value] ?? 0) + ($byStatus[BookingStatus::PaymentApproved->value] ?? 0)),
                'cancelled' => (int) (($byStatus[BookingStatus::Cancelled->value] ?? 0) + ($byStatus[BookingStatus::Rejected->value] ?? 0)),
            ],
            'totalGroups' => (int) $counts->sum(),
            'featured' => $featured,
        ]);
    }
}
