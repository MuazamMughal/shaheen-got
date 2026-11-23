@php($settingsService = app(\App\Services\SettingsService::class))
@php($theme = $settingsService->theme())
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="{{ $theme['secondary'] }}">

        <title inertia>{{ config('app.name', 'Shaheen Group of Travels') }}</title>
        <link rel="icon" href="{{ $settingsService->publicSettings()['site']['favicon'] }}">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700|poppins:500,600,700&display=swap" rel="stylesheet" />

        {{-- Brand colors from Admin → Settings (validated hex values only). --}}
        <style id="brand-theme">
            :root {
                --brand-primary: {{ $theme['primary'] }};
                --brand-secondary: {{ $theme['secondary'] }};
                --brand-tertiary: {{ $theme['tertiary'] }};
            }
        </style>

        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
