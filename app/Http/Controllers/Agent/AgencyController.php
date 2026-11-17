<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Admin\Concerns\ManagesMasterRecords;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The signed-in agent updates their own agency profile. */
class AgencyController extends Controller
{
    use ManagesMasterRecords; // upload helpers

    public function update(Request $request): RedirectResponse
    {
        $request->merge([
            'whatsapp' => $request->whatsapp ? preg_replace('/\D/', '', $request->whatsapp) : null,
            'notification_emails' => collect($request->input('notification_emails', []))
                ->map(fn ($e) => mb_strtolower(trim((string) $e)))->filter()->unique()->values()->all(),
        ]);

        $data = $request->validate([
            'agency_name' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'whatsapp' => ['nullable', 'digits_between:10,15'],
            'notification_emails' => ['array', 'max:5'],
            'notification_emails.*' => ['email', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['boolean'],
        ], [
            'notification_emails.max' => 'You can add up to 5 notification emails.',
            'notification_emails.*.email' => 'Each notification email must be a valid address.',
        ]);

        $user = $request->user();
        $agency = $user->agency()->firstOrNew([], ['owner_name' => $user->name]);
        $agency->fill(collect($data)->only('agency_name', 'city', 'address', 'phone', 'whatsapp', 'notification_emails')->all());
        $agency->logo = $this->handleUpload($request, 'logo', 'agencies', $agency->logo);
        $agency->save();

        return back()->with('success', 'Agency profile saved.');
    }
}
