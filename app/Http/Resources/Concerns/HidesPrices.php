<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Http\Request;

/**
 * Use in every API/Inertia resource that exposes money. Price fields wrapped
 * in price() are left out of the payload entirely (not nulled) for staff
 * without `prices.view`, so they never reach the browser.
 *
 *   'adult_price' => $this->price($request, $this->adult_price),
 */
trait HidesPrices
{
    protected function canViewPrices(Request $request): bool
    {
        return (bool) $request->user()?->canViewPrices();
    }

    protected function price(Request $request, mixed $value): mixed
    {
        return $this->when($this->canViewPrices($request), $value);
    }

    /** Merge a block of price fields at once. */
    protected function prices(Request $request, array $fields): mixed
    {
        return $this->mergeWhen($this->canViewPrices($request), $fields);
    }
}
