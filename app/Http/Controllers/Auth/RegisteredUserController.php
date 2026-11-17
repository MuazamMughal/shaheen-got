<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Public registration only ever creates an agent (with agency profile),
     * pending admin approval.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'whatsapp' => $request->whatsapp ? preg_replace('/\D/', '', $request->whatsapp) : null,
        ]);

        $data = $request->validate([
            'agency_name' => ['required', 'string', 'max:160'],
            'name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'whatsapp' => ['nullable', 'digits_between:10,15'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms' => ['accepted'],
        ], [
            'terms.accepted' => 'Please confirm that your details are correct.',
            'phone.regex' => 'Enter a valid phone number.',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
            ]);
            $user->forceFill(['type' => UserType::Agent, 'status' => UserStatus::Pending])->save();

            $user->agency()->create([
                'agency_name' => $data['agency_name'],
                'owner_name' => $data['name'],
                'city' => $data['city'],
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'],
                'whatsapp' => $data['whatsapp'] ?? null,
                'notification_emails' => [],
            ]);

            return $user;
        });

        event(new Registered($user));
        app(Notifier::class)->agentRegistered($user->load('agency'));

        Auth::login($user);

        return redirect($user->homeRoute());
    }
}
