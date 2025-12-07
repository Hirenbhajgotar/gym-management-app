<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

<head>
    <script src="https://cdn.tailwindcss.com"></script>
    @include('partials.head')
    <style>
        .animate-slide-up {
            animation: slideUp 0.8s ease-out;
        }

        .animate-float {
            animation: float 6s ease-in-out infinite;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }
        }

        .feature-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0.08) 100%);
            transform: translateY(-5px);
        }
    </style>
</head>

<body class="min-h-screen bg-gradient-to-br from-neutral-950 via-purple-950 to-neutral-900 antialiased">
    <!-- Background Effects -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div
            class="absolute -top-40 -right-40 w-80 h-80 bg-gradient-to-r from-purple-500 to-cyan-500 rounded-full blur-3xl opacity-20">
        </div>
        <div
            class="absolute -bottom-40 -left-40 w-80 h-80 bg-gradient-to-r from-cyan-500 to-purple-500 rounded-full blur-3xl opacity-20">
        </div>
    </div>

    <div class="relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">
        <div class="flex w-full max-w-xl flex-col gap-2">
            <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium animate-slide-up">
                <span
                    class="flex h-12 w-12 mb-2 items-center justify-center rounded-xl bg-gradient-to-r from-purple-500 to-cyan-500">
                    <x-app-logo-icon class="size-10 fill-current text-white" />
                </span>
                <span
                    class="text-2xl font-bold bg-gradient-to-r from-purple-400 to-cyan-400 bg-clip-text text-transparent">
                    {{ config('app.name') }}
                </span>
            </a>
            <div class="flex flex-col gap-6 animate-float">
                {{ $slot }}
            </div>
        </div>
    </div>
    @fluxScripts
</body>

</html>
