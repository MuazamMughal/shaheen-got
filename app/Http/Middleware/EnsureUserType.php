<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route group to one side of the app.
 * Usage: ->middleware('user.type:admin') or ('user.type:agent').
 * "admin" means super_admin or staff.
 */
class EnsureUserType
{
    public function handle(Request $request, Closure $next, string $side): Response
    {
        $user = $request->user();

        $allowed = match ($side) {
            'admin' => $user?->isAdminSide(),
            'agent' => $user?->isAgent(),
            default => false,
        };

        if (! $allowed) {
            // Send the user to their own area instead of a dead end.
            return $user
                ? redirect($user->homeRoute())
                : redirect()->route('login');
        }

        return $next($request);
    }
}
