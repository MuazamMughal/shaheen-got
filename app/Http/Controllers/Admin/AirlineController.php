<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesMasterRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AirlineRequest;
use App\Models\Airline;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AirlineController extends Controller
{
    use ManagesMasterRecords;

    public function index(): Response
    {
        return Inertia::render('Admin/Airlines/Index', [
            'airlines' => Airline::orderBy('name')->get(),
        ]);
    }

    public function store(AirlineRequest $request): RedirectResponse
    {
        $airline = new Airline($request->safe()->only('name', 'iata_code'));
        $airline->is_active = $request->boolean('is_active', true);
        $airline->logo = $this->handleUpload($request, 'logo', 'airlines', null);
        $airline->save();

        return back()->with('success', 'Airline created.');
    }

    public function update(AirlineRequest $request, Airline $airline): RedirectResponse
    {
        $airline->fill($request->safe()->only('name', 'iata_code'));
        $airline->is_active = $request->boolean('is_active');
        $airline->logo = $this->handleUpload($request, 'logo', 'airlines', $airline->logo);
        $airline->save();

        return back()->with('success', 'Airline updated.');
    }

    public function toggle(Airline $airline): RedirectResponse
    {
        return $this->toggleActive($airline, 'Airline');
    }

    public function destroy(Airline $airline): RedirectResponse
    {
        return $this->destroyRecord($airline, 'Airline', ['logo']);
    }
}
