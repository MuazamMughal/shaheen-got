<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ManagesMasterRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BaggageTypeRequest;
use App\Models\BaggageType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BaggageTypeController extends Controller
{
    use ManagesMasterRecords;

    public function index(): Response
    {
        return Inertia::render('Admin/BaggageTypes/Index', [
            'baggageTypes' => BaggageType::ordered()->get(),
        ]);
    }

    public function store(BaggageTypeRequest $request): RedirectResponse
    {
        BaggageType::create([
            ...$request->safe()->only('label', 'description'),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => BaggageType::nextSortOrder(),
        ]);

        return back()->with('success', 'Baggage type created.');
    }

    public function update(BaggageTypeRequest $request, BaggageType $baggageType): RedirectResponse
    {
        $baggageType->update([
            ...$request->safe()->only('label', 'description'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Baggage type updated.');
    }

    public function toggle(BaggageType $baggageType): RedirectResponse
    {
        return $this->toggleActive($baggageType, 'Baggage type');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->applyOrder(BaggageType::class, $this->validateOrder($request, 'baggage_types'));

        return back()->with('success', 'Order saved.');
    }

    public function destroy(BaggageType $baggageType): RedirectResponse
    {
        return $this->destroyRecord($baggageType, 'Baggage type');
    }
}
