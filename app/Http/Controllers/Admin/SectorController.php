<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TripType;
use App\Http\Controllers\Admin\Concerns\ManagesMasterRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SectorRequest;
use App\Models\Category;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SectorController extends Controller
{
    use ManagesMasterRecords;

    public function index(Request $request): Response
    {
        $categoryId = $request->integer('category') ?: null;

        return Inertia::render('Admin/Sectors/Index', [
            'sectors' => Sector::query()
                ->with('category:id,name')
                ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
                ->join('categories', 'categories.id', '=', 'sectors.category_id')
                ->orderBy('categories.sort_order')
                ->orderBy('sectors.sort_order')
                ->orderBy('sectors.id')
                ->select('sectors.*')
                ->get()
                ->map(fn (Sector $s) => [
                    ...$s->only('id', 'category_id', 'name', 'origin', 'destination', 'sort_order', 'is_active'),
                    'trip_type' => $s->trip_type->value,
                    'trip_type_label' => $s->trip_type->label(),
                    'category' => $s->category?->name,
                ]),
            'categories' => Category::ordered()->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name]),
            'tripTypes' => TripType::options(),
            'filters' => ['category' => $categoryId],
        ]);
    }

    public function store(SectorRequest $request): RedirectResponse
    {
        $data = $request->safe()->only('category_id', 'name', 'origin', 'destination', 'trip_type');
        Sector::create([
            ...$data,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => Sector::nextSortOrder(fn ($q) => $q->where('category_id', $data['category_id'])),
        ]);

        return back()->with('success', 'Sector created.');
    }

    public function update(SectorRequest $request, Sector $sector): RedirectResponse
    {
        $sector->update([
            ...$request->safe()->only('category_id', 'name', 'origin', 'destination', 'trip_type'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Sector updated.');
    }

    public function toggle(Sector $sector): RedirectResponse
    {
        return $this->toggleActive($sector, 'Sector');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->applyOrder(Sector::class, $this->validateOrder($request, 'sectors'));

        return back()->with('success', 'Order saved.');
    }

    public function destroy(Sector $sector): RedirectResponse
    {
        return $this->destroyRecord($sector, 'Sector');
    }
}
