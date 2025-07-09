<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $title ?? 'Page Title' }}</title>
    {{-- <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script> --}}
    {{-- @include('partials.head') --}}

    {{-- @livewireStyles --}}
    @filamentStyles
    @vite(['resources/css/app.css'])
</head>

<body>
    {{ $slot }}


    @filamentScripts
    @vite(['resources/js/app.js'])
    {{-- @livewireScripts --}}
    {{-- @fluxScripts --}}
</body>

</html>
