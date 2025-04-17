<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Filament') }}</title>

    {{-- Filament and Livewire styles --}}
    @livewireStyles
    @filamentStyles
</head>
<body class="font-sans antialiased">
    {{-- Main content slot --}}
    {{ $slot }}

    {{-- Livewire and Filament scripts --}}
    @livewireScripts
    @filamentScripts
</body>
</html>
