<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $emails = collect($this->input('admin_emails', []))
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))
            ->filter()->unique()->values()->all();

        $this->merge(['admin_emails' => $emails]);
    }

    public function rules(): array
    {
        $hex = ['required', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'];

        return [
            'theme_primary' => $hex,
            'theme_secondary' => $hex,
            'theme_tertiary' => $hex,
            'site_name' => ['required', 'string', 'max:120'],
            'site_tagline' => ['nullable', 'string', 'max:160'],
            'site_ticker' => ['nullable', 'string', 'max:500'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico', 'max:512'],
            'remove_logo' => ['boolean'],
            'remove_favicon' => ['boolean'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_whatsapp' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9 ]{8,20}$/'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_address' => ['nullable', 'string', 'max:500'],
            'admin_emails' => ['array', 'max:10'],
            'admin_emails.*' => ['email', 'max:255'],
            'low_seat_threshold' => ['required', 'integer', 'min:0', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'theme_primary.regex' => 'Use a hex color like #F7F8FC.',
            'theme_secondary.regex' => 'Use a hex color like #0B1B4D.',
            'theme_tertiary.regex' => 'Use a hex color like #D4A84B.',
            'contact_whatsapp.regex' => 'Use digits with country code, e.g. 923001234567.',
            'admin_emails.*.email' => 'Each notification email must be a valid address.',
        ];
    }
}
