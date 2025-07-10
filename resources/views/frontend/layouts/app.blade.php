<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Laravel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>

    <!-- Styles -->
    @livewireStyles
    @filamentStyles
    @vite(['resources/css/app.css'])

    <style>
        [x-cloak] {
            display: none !important;
        }

        /* Ensure form container is centered and full width */
        .filament-forms-container {
            width: 100% !important;
            max-width: 100% !important;
            margin: 0 auto !important;
            text-align: left !important;
        }

        /* Force label visibility and styling */
        .fi-fo-field-wrp-label label,
        .fi-field-label {
            color: #374151 !important;
            font-weight: 500 !important;
            display: block !important;
            margin-bottom: 0.5rem !important;
        }

        /* Fix input styling to prevent overrides */
        .fi-input,
        .fi-select-input,
        .fi-textarea {
            width: 100% !important;
            background-color: white !important;
            border: 1px solid #d1d5db !important;
            color: #111827 !important;
        }

        /* Fix wizard alignment */
        .fi-wizard {
            text-align: left !important;
            width: 100% !important;
        }

        /* Center button group */
        .fi-ac-btn-group {
            justify-content: center !important;
            margin-top: 1rem !important;
        }
    </style>
</head>

<body class="antialiased">
    <main class="min-h-screen bg-gray-50">
        {{ $slot }}
    </main>

    @livewireScripts
    @filamentScripts
</body>

</html>
