<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ config('app.name', 'ERP') }}</title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.ts',
    ])

    <x-inertia::head />
</head>
<body class="min-h-screen bg-gray-50 antialiased">
    <x-inertia::app />
</body>
</html>