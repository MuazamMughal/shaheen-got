<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\AgentController;
use App\Http\Controllers\Admin\AirlineController;
use App\Http\Controllers\Admin\BaggageTypeController;
use App\Http\Controllers\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\FlightController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SectorController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Agent\AgencyController;
use App\Http\Controllers\Agent\BookingController as AgentBookingController;
use App\Http\Controllers\Agent\DashboardController as AgentDashboardController;
use App\Http\Controllers\Agent\GroupController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
| Public
*/
Route::get('/', fn () => Inertia::render('Public/Home', [
    'categories' => Category::active()->ordered()->get(['id', 'name', 'slug', 'country', 'image', 'flag_icon']),
]))->name('home');

/*
| Shared (any signed-in user)
*/
Route::middleware('auth')->group(function () {
    // Generic landing used by Breeze auth flows: sends each user to their own area.
    Route::get('/dashboard', fn (Request $request) => redirect($request->user()->homeRoute()))
        ->name('dashboard');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'read'])->name('notifications.read');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

/*
| Admin panel (super_admin + staff)
*/
Route::prefix('admin')->name('admin.')
    ->middleware(['auth', 'user.type:admin'])
    ->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        // Master data. Updates with files use POST + _method=PUT (multipart).
        $masters = [
            'categories' => [CategoryController::class, 'categories.manage', 'category', true],
            'sectors' => [SectorController::class, 'sectors.manage', 'sector', true],
            'airlines' => [AirlineController::class, 'airlines.manage', 'airline', false],
            'baggage-types' => [BaggageTypeController::class, 'baggage.manage', 'baggage_type', true],
        ];
        foreach ($masters as $uri => [$controller, $permission, $param, $sortable]) {
            $name = str_replace('-', '_', $uri);
            Route::middleware("can:{$permission}")->group(function () use ($uri, $name, $controller, $param, $sortable) {
                if ($sortable) {
                    Route::post("{$uri}/reorder", [$controller, 'reorder'])->name("{$name}.reorder");
                }
                Route::patch("{$uri}/{{$param}}/toggle", [$controller, 'toggle'])->name("{$name}.toggle");
                Route::resource($uri, $controller)->only(['index', 'store', 'update', 'destroy'])
                    ->names($name)->parameters([$uri => $param]);
            });
        }

        // Flights. Each action has its own permission.
        Route::get('flights', [FlightController::class, 'index'])->middleware('can:flights.view')->name('flights.index');
        Route::middleware('can:flights.create')->group(function () {
            Route::get('flights/create', [FlightController::class, 'create'])->name('flights.create');
            Route::post('flights', [FlightController::class, 'store'])->name('flights.store');
            Route::get('flights/{flight}/duplicate', [FlightController::class, 'duplicate'])->name('flights.duplicate');
        });
        Route::middleware('can:flights.edit')->group(function () {
            Route::get('flights/{flight}/edit', [FlightController::class, 'edit'])->name('flights.edit');
            Route::put('flights/{flight}', [FlightController::class, 'update'])->name('flights.update');
        });
        Route::patch('flights/{flight}/publish', [FlightController::class, 'togglePublish'])
            ->middleware('can:flights.publish')->name('flights.publish');
        Route::delete('flights/{flight}', [FlightController::class, 'destroy'])
            ->middleware('can:flights.delete')->name('flights.destroy');

        // Bookings. Each action has its own permission.
        Route::middleware('can:bookings.view')->group(function () {
            Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
            Route::get('bookings/{booking}', [AdminBookingController::class, 'show'])->name('bookings.show');
            Route::get('flights/{flight}/bookings', [AdminBookingController::class, 'flight'])->name('flights.bookings');
        });
        Route::middleware('can:bookings.edit')->group(function () {
            Route::get('bookings/{booking}/edit', [AdminBookingController::class, 'edit'])->name('bookings.edit');
            Route::put('bookings/{booking}', [AdminBookingController::class, 'update'])->name('bookings.update');
        });
        Route::patch('bookings/{booking}/approve-payment', [AdminBookingController::class, 'approvePayment'])->middleware('can:bookings.approve_payment')->name('bookings.approve-payment');
        Route::patch('bookings/{booking}/confirm', [AdminBookingController::class, 'confirm'])->middleware('can:bookings.confirm')->name('bookings.confirm');
        Route::middleware('can:bookings.reject')->group(function () {
            Route::patch('bookings/{booking}/reject', [AdminBookingController::class, 'reject'])->name('bookings.reject');
            Route::patch('bookings/{booking}/cancel', [AdminBookingController::class, 'cancel'])->name('bookings.cancel');
        });
        Route::delete('bookings/{booking}', [AdminBookingController::class, 'destroy'])->middleware('can:bookings.delete')->name('bookings.destroy');
        Route::get('bookings/{booking}/pdf', [AdminBookingController::class, 'pdf'])->middleware('can:bookings.export')->name('bookings.pdf');

        // Agents. {agent} only binds to users of type "agent" (AppServiceProvider).
        Route::middleware('can:agents.view')->group(function () {
            Route::get('agents', [AgentController::class, 'index'])->name('agents.index');
            Route::get('agents/{agent}', [AgentController::class, 'show'])->name('agents.show');
        });
        Route::put('agents/{agent}', [AgentController::class, 'update'])->middleware('can:agents.edit')->name('agents.update');
        Route::middleware('can:agents.approve')->group(function () {
            Route::patch('agents/{agent}/approve', [AgentController::class, 'approve'])->name('agents.approve');
            Route::patch('agents/{agent}/reject', [AgentController::class, 'reject'])->name('agents.reject');
        });
        Route::middleware('can:agents.suspend')->group(function () {
            Route::patch('agents/{agent}/suspend', [AgentController::class, 'suspend'])->name('agents.suspend');
            Route::patch('agents/{agent}/reactivate', [AgentController::class, 'reactivate'])->name('agents.reactivate');
        });

        Route::get('activity', [ActivityController::class, 'index'])->middleware('can:reports.view')->name('activity.index');

        Route::middleware('can:settings.manage')->group(function () {
            Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
            Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
            Route::post('settings/test-email', [SettingsController::class, 'testEmail'])
                ->middleware('throttle:5,1')->name('settings.test-email');
        });

        Route::resource('roles', RoleController::class)->except('show')
            ->middleware('can:roles.manage');

        // {staff} only binds to users of type "staff" (AppServiceProvider).
        Route::middleware('can:staff.manage')->group(function () {
            Route::resource('staff', StaffController::class)->except(['show', 'destroy'])
                ->parameters(['staff' => 'staff']);
            Route::patch('staff/{staff}/toggle-status', [StaffController::class, 'toggleStatus'])
                ->name('staff.toggle-status');
        });
    });

/*
| Agent portal
*/
Route::prefix('agent')->name('agent.')
    ->middleware(['auth', 'user.type:agent'])
    ->group(function () {
        Route::get('/pending', function (Request $request) {
            if ($request->user()->isApproved()) {
                return redirect()->route('agent.dashboard');
            }

            return Inertia::render('Agent/Pending', [
                'status' => $request->user()->status->value,
            ]);
        })->name('pending');

        // Pending agents may complete their agency profile too.
        Route::post('agency', [AgencyController::class, 'update'])->name('agency.update');

        Route::middleware('agent.approved')->group(function () {
            Route::get('/', AgentDashboardController::class)->name('dashboard');
            Route::get('groups', [GroupController::class, 'index'])->name('groups.index');
            Route::get('groups/{category:slug}', [GroupController::class, 'show'])->name('groups.show');

            Route::get('flights/{flight}/book', [AgentBookingController::class, 'create'])->name('bookings.create');
            Route::post('flights/{flight}/book', [AgentBookingController::class, 'store'])
                ->middleware('throttle:20,1')->name('bookings.store');
            Route::get('bookings', [AgentBookingController::class, 'index'])->name('bookings.index');
            Route::get('bookings/{booking}', [AgentBookingController::class, 'show'])->name('bookings.show');
            Route::get('bookings/{booking}/edit', [AgentBookingController::class, 'edit'])->name('bookings.edit');
            Route::put('bookings/{booking}', [AgentBookingController::class, 'update'])->name('bookings.update');
            Route::patch('bookings/{booking}/cancel', [AgentBookingController::class, 'cancel'])->name('bookings.cancel');
            Route::get('bookings/{booking}/pdf', [AgentBookingController::class, 'pdf'])->name('bookings.pdf');
        });
    });

require __DIR__.'/auth.php';
