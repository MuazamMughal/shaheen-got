<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Agents must be approved by an admin before using the portal. */
class EnsureAgentApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isAgent() && ! $user->isApproved()) {
            return redirect()->route('agent.pending');
        }

        return $next($request);
    }
}
