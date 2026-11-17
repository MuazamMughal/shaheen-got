<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Logs out users who were rejected or suspended while signed in. */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status?->isBlocked()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account is '.$user->status->value.'.'
                    .($user->status_reason ? ' Reason: '.$user->status_reason : '')
                    .' Please contact Shaheen Group of Travels.',
            ]);
        }

        return $next($request);
    }
}
