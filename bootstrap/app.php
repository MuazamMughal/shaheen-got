<?php

use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureAgentApproved;
use App\Http\Middleware\EnsureUserType;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsureAccountActive::class,
            SecurityHeaders::class,
        ]);

        $middleware->alias([
            'user.type' => EnsureUserType::class,
            'agent.approved' => EnsureAgentApproved::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Branded error pages for browser visits. JSON requests keep plain responses;
        // 500/503 keep Laravel's debug page while developing locally.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson()) {
                return $response;
            }

            if ($status === 419) {
                return back()->with('error', 'Your session expired. Please try again.');
            }

            $friendly = in_array($status, [403, 404], true)
                || (in_array($status, [500, 503], true) && ! app()->hasDebugModeEnabled());

            if ($friendly) {
                $message = $e instanceof HttpExceptionInterface && $status === 403 && $e->getMessage() !== '' && $e->getMessage() !== 'This action is unauthorized.'
                    ? $e->getMessage()
                    : null;

                return Inertia::render('Error', ['status' => $status, 'message' => $message])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
