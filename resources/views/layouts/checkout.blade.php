<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Complete Payment - ' . config('app.name'))</title>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                        'float': 'float 6s ease-in-out infinite',
                        'float-delayed': 'float 6s ease-in-out infinite 2s',
                        'float-slow': 'float 8s ease-in-out infinite',
                        'float-slow-delayed': 'float 8s ease-in-out infinite 3s',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': {
                                transform: 'translateY(0px) rotate(0deg)'
                            },
                            '50%': {
                                transform: 'translateY(-20px) rotate(180deg)'
                            }
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) translateX(0px) rotate(0deg) scale(1);
                opacity: 0.6;
            }

            25% {
                transform: translateY(-25px) translateX(20px) rotate(90deg) scale(1.2);
                opacity: 0.8;
            }

            50% {
                transform: translateY(-50px) translateX(-15px) rotate(180deg) scale(0.8);
                opacity: 0.4;
            }

            75% {
                transform: translateY(-25px) translateX(-20px) rotate(270deg) scale(1.1);
                opacity: 0.7;
            }
        }

        @keyframes float-delayed {

            0%,
            100% {
                transform: translateY(0px) translateX(0px) rotate(0deg) scale(1);
                opacity: 0.5;
            }

            25% {
                transform: translateY(-30px) translateX(-20px) rotate(90deg) scale(1.3);
                opacity: 0.7;
            }

            50% {
                transform: translateY(-60px) translateX(25px) rotate(180deg) scale(0.7);
                opacity: 0.3;
            }

            75% {
                transform: translateY(-30px) translateX(10px) rotate(270deg) scale(1.1);
                opacity: 0.6;
            }
        }

        @keyframes float-slow {

            0%,
            100% {
                transform: translateY(0px) translateX(0px) rotate(0deg) scale(1);
                opacity: 0.4;
            }

            33% {
                transform: translateY(-35px) translateX(30px) rotate(120deg) scale(1.4);
                opacity: 0.8;
            }

            66% {
                transform: translateY(-70px) translateX(-25px) rotate(240deg) scale(0.6);
                opacity: 0.2;
            }
        }

        @keyframes float-slow-delayed {

            0%,
            100% {
                transform: translateY(0px) translateX(0px) rotate(0deg) scale(1);
                opacity: 0.5;
            }

            33% {
                transform: translateY(-40px) translateX(-30px) rotate(120deg) scale(1.2);
                opacity: 0.6;
            }

            66% {
                transform: translateY(-80px) translateX(20px) rotate(240deg) scale(0.8);
                opacity: 0.3;
            }
        }

        .particle {
            position: absolute;
            border-radius: 50%;
            pointer-events: none;
            z-index: 1;
            animation: float 6s ease-in-out infinite;
        }

        /* Test animation */
        @keyframes testPulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.5);
                opacity: 0.5;
            }
        }

        .particle-1 {
            animation: float 6s ease-in-out infinite;
        }

        .particle-2 {
            animation: float-delayed 7s ease-in-out infinite;
        }

        .particle-3 {
            animation: float-slow 8s ease-in-out infinite;
        }

        .particle-4 {
            animation: float-slow-delayed 9s ease-in-out infinite;
        }

        .particle-5 {
            animation: float 5s ease-in-out infinite;
        }

        .particle-6 {
            animation: float-delayed 6.5s ease-in-out infinite;
        }

        .particle-7 {
            animation: float-slow 7.5s ease-in-out infinite;
        }

        .particle-8 {
            animation: float-slow-delayed 8.5s ease-in-out infinite;
        }

        .particle-9 {
            animation: float 6.2s ease-in-out infinite;
        }

        .particle-10 {
            animation: float-delayed 7.8s ease-in-out infinite;
        }

        .particle-1 {
            width: 8px;
            height: 8px;
            background: linear-gradient(45deg, rgba(59, 130, 246, 0.6), rgba(99, 102, 241, 0.4));
            top: 10%;
            left: 10%;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.3);
        }

        .particle-2 {
            width: 12px;
            height: 12px;
            background: linear-gradient(45deg, rgba(139, 92, 246, 0.5), rgba(168, 85, 247, 0.3));
            top: 20%;
            right: 15%;
            box-shadow: 0 0 15px rgba(139, 92, 246, 0.3);
        }

        .particle-3 {
            width: 6px;
            height: 6px;
            background: linear-gradient(45deg, rgba(16, 185, 129, 0.7), rgba(5, 150, 105, 0.5));
            top: 40%;
            left: 20%;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.4);
        }

        .particle-4 {
            width: 10px;
            height: 10px;
            background: linear-gradient(45deg, rgba(245, 158, 11, 0.6), rgba(217, 119, 6, 0.4));
            top: 60%;
            right: 25%;
            box-shadow: 0 0 12px rgba(245, 158, 11, 0.3);
        }

        .particle-5 {
            width: 14px;
            height: 14px;
            background: linear-gradient(45deg, rgba(239, 68, 68, 0.5), rgba(220, 38, 38, 0.3));
            top: 30%;
            left: 70%;
            box-shadow: 0 0 18px rgba(239, 68, 68, 0.3);
        }

        .particle-6 {
            width: 7px;
            height: 7px;
            background: linear-gradient(45deg, rgba(6, 182, 212, 0.6), rgba(8, 145, 178, 0.4));
            top: 70%;
            right: 10%;
            box-shadow: 0 0 9px rgba(6, 182, 212, 0.4);
        }

        .particle-7 {
            width: 9px;
            height: 9px;
            background: linear-gradient(45deg, rgba(236, 72, 153, 0.6), rgba(219, 39, 119, 0.4));
            top: 50%;
            left: 5%;
            box-shadow: 0 0 11px rgba(236, 72, 153, 0.3);
        }

        .particle-8 {
            width: 11px;
            height: 11px;
            background: linear-gradient(45deg, rgba(34, 197, 94, 0.6), rgba(22, 163, 74, 0.4));
            top: 80%;
            right: 40%;
            box-shadow: 0 0 13px rgba(34, 197, 94, 0.4);
        }

        .particle-9 {
            width: 5px;
            height: 5px;
            background: linear-gradient(45deg, rgba(147, 51, 234, 0.7), rgba(124, 58, 237, 0.5));
            top: 15%;
            left: 50%;
            box-shadow: 0 0 7px rgba(147, 51, 234, 0.4);
        }

        .particle-10 {
            width: 13px;
            height: 13px;
            background: linear-gradient(45deg, rgba(251, 146, 60, 0.6), rgba(234, 88, 12, 0.4));
            top: 85%;
            left: 30%;
            box-shadow: 0 0 16px rgba(251, 146, 60, 0.3);
        }

        .geometric-shape {
            position: absolute;
            pointer-events: none;
            opacity: 0.08;
        }

        .shape-1 {
            width: 60px;
            height: 60px;
            background: linear-gradient(45deg, rgba(59, 130, 246, 0.1), rgba(99, 102, 241, 0.1));
            clip-path: polygon(50% 0%, 0% 100%, 100% 100%);
            top: 25%;
            right: 5%;
            animation: shape-float-1 8s ease-in-out infinite;
        }

        .shape-2 {
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, rgba(139, 92, 246, 0.08), rgba(168, 85, 247, 0.08));
            clip-path: polygon(25% 0%, 75% 0%, 100% 50%, 75% 100%, 25% 100%, 0% 50%);
            top: 65%;
            left: 8%;
            animation: shape-float-2 10s ease-in-out infinite;
        }

        .shape-3 {
            width: 50px;
            height: 50px;
            background: linear-gradient(45deg, rgba(16, 185, 129, 0.12), rgba(5, 150, 105, 0.12));
            clip-path: polygon(50% 0%, 61% 35%, 98% 35%, 68% 57%, 79% 91%, 50% 70%, 21% 91%, 32% 57%, 2% 35%, 39% 35%);
            top: 45%;
            right: 30%;
            animation: shape-float-3 12s ease-in-out infinite;
        }

        @keyframes shape-float-1 {

            0%,
            100% {
                transform: translateY(0px) translateX(0px) rotate(0deg) scale(1);
                opacity: 0.08;
            }

            25% {
                transform: translateY(-20px) translateX(15px) rotate(90deg) scale(1.1);
                opacity: 0.12;
            }

            50% {
                transform: translateY(-40px) translateX(-10px) rotate(180deg) scale(0.9);
                opacity: 0.06;
            }

            75% {
                transform: translateY(-20px) translateX(-15px) rotate(270deg) scale(1.05);
                opacity: 0.1;
            }
        }

        @keyframes shape-float-2 {

            0%,
            100% {
                transform: translateY(0px) translateX(0px) rotate(0deg) scale(1);
                opacity: 0.06;
            }

            33% {
                transform: translateY(-30px) translateX(-20px) rotate(120deg) scale(1.2);
                opacity: 0.1;
            }

            66% {
                transform: translateY(-60px) translateX(25px) rotate(240deg) scale(0.8);
                opacity: 0.04;
            }
        }

        @keyframes shape-float-3 {

            0%,
            100% {
                transform: translateY(0px) translateX(0px) rotate(0deg) scale(1);
                opacity: 0.1;
            }

            20% {
                transform: translateY(-15px) translateX(30px) rotate(72deg) scale(1.15);
                opacity: 0.14;
            }

            40% {
                transform: translateY(-45px) translateX(10px) rotate(144deg) scale(0.85);
                opacity: 0.06;
            }

            60% {
                transform: translateY(-30px) translateX(-25px) rotate(216deg) scale(1.1);
                opacity: 0.12;
            }

            80% {
                transform: translateY(-60px) translateX(-5px) rotate(288deg) scale(0.9);
                opacity: 0.08;
            }
        }
    </style>

    @stack('head')
</head>

<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen flex items-center justify-center p-4">
    <!-- Loading Overlay -->
    <div id="loading-overlay"
        class="hidden fixed inset-0 bg-slate-900/80 backdrop-blur-sm z-50 flex items-center justify-center">
        <div class="bg-white rounded-2xl p-8 max-w-md w-full mx-4 shadow-2xl">
            <div class="text-center">
                <!-- Animated Success Icon -->
                <div
                    class="mx-auto w-20 h-20 bg-gradient-to-br from-green-400 to-emerald-500 rounded-full flex items-center justify-center mb-6 animate-bounce">
                    <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                    </svg>
                </div>

                <!-- Success Message -->
                <h3 class="text-2xl font-bold text-gray-900 mb-2">@yield('success-title', 'Payment Successful!')</h3>
                <p class="text-gray-600 mb-6">@yield('success-message', 'Your payment has been processed successfully')</p>

                <!-- Loading Spinner -->
                <div class="flex items-center justify-center space-x-2 mb-4">
                    <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 0s;"></div>
                    <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 0.2s;"></div>
                    <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce" style="animation-delay: 0.4s;"></div>
                </div>

                <p class="text-sm text-gray-500 font-medium">@yield('redirect-message', 'Redirecting...')</p>
            </div>
        </div>
    </div>

    <!-- Background decorations -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 -right-40 w-80 h-80 bg-blue-400/10 rounded-full blur-3xl animate-pulse-slow"></div>
        <div class="absolute -bottom-40 -left-40 w-80 h-80 bg-indigo-400/10 rounded-full blur-3xl animate-pulse-slow"
            style="animation-delay: 1s;"></div>

        <!-- Animated Particles -->
        <div class="particle particle-1"></div>
        <div class="particle particle-2"></div>
        <div class="particle particle-3"></div>
        <div class="particle particle-4"></div>
        <div class="particle particle-5"></div>
        <div class="particle particle-6"></div>
        <div class="particle particle-7"></div>
        <div class="particle particle-8"></div>
        <div class="particle particle-9"></div>
        <div class="particle particle-10"></div>

        <!-- Geometric Shapes -->
        <div class="geometric-shape shape-1"></div>
        <div class="geometric-shape shape-2"></div>
        <div class="geometric-shape shape-3"></div>
    </div>

    @yield('content')

    @stack('scripts')

    <script>
        // Dynamic particle animation system
        document.addEventListener('DOMContentLoaded', function() {
            const backgroundContainer = document.querySelector('.fixed.inset-0.overflow-hidden');

            // Verify static particles exist
            const staticParticles = backgroundContainer.querySelectorAll('.particle');

            // Add a visible test animation to verify animations work
            const testElement = document.createElement('div');
            testElement.style.cssText = `
                position: absolute;
                width: 15px;
                height: 15px;
                background: #ff0000;
                border-radius: 50%;
                top: 10px;
                right: 10px;
                z-index: 9999;
                animation: testPulse 1s infinite;
                box-shadow: 0 0 15px #ff0000;
            `;
            backgroundContainer.appendChild(testElement);

            // Remove the test element after 10 seconds
            setTimeout(() => {
                if (testElement.parentNode) {
                    testElement.parentNode.removeChild(testElement);
                }
            }, 10000);

            // Create dynamic particles
            function createParticle() {
                const particle = document.createElement('div');
                const size = Math.random() * 8 + 4; // 4-12px
                const colors = [
                    'rgba(59, 130, 246, 0.3)', // blue
                    'rgba(139, 92, 246, 0.25)', // purple
                    'rgba(16, 185, 129, 0.35)', // green
                    'rgba(245, 158, 11, 0.3)', // amber
                    'rgba(239, 68, 68, 0.25)', // red
                    'rgba(6, 182, 212, 0.35)', // cyan
                    'rgba(236, 72, 153, 0.3)', // pink
                ];

                const animationDuration = Math.random() * 4 + 6; // 6-10 seconds
                const startX = Math.random() * 100; // 0-100%
                const startY = Math.random() * 100; // 0-100%

                particle.className = 'dynamic-particle';
                particle.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    background: linear-gradient(45deg, ${colors[Math.floor(Math.random() * colors.length)]}, transparent);
                    border-radius: 50%;
                    pointer-events: none;
                    top: ${startY}%;
                    left: ${startX}%;
                    animation: dynamicFloat ${animationDuration}s ease-in-out infinite;
                    opacity: 0;
                    z-index: 1;
                `;

                backgroundContainer.appendChild(particle);

                // Remove particle after animation
                setTimeout(() => {
                    if (particle.parentNode) {
                        particle.parentNode.removeChild(particle);
                    }
                }, animationDuration * 1000);
            }

            // Create new particles periodically
            setInterval(createParticle, 3000);

            // Create initial particles
            for (let i = 0; i < 8; i++) {
                setTimeout(createParticle, i * 400);
            }

            // Add some sparkle effects
            function createSparkle() {
                const sparkle = document.createElement('div');
                const size = Math.random() * 3 + 1; // 1-4px
                const colors = ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444'];

                sparkle.style.cssText = `
                    position: absolute;
                    width: ${size}px;
                    height: ${size}px;
                    background: ${colors[Math.floor(Math.random() * colors.length)]};
                    border-radius: 50%;
                    pointer-events: none;
                    top: ${Math.random() * 100}%;
                    left: ${Math.random() * 100}%;
                    animation: sparkle ${Math.random() * 2 + 1}s ease-in-out infinite;
                    opacity: 0.8;
                    z-index: 2;
                `;

                backgroundContainer.appendChild(sparkle);

                setTimeout(() => {
                    if (sparkle.parentNode) {
                        sparkle.parentNode.removeChild(sparkle);
                    }
                }, 3000);
            }

            // Create sparkles occasionally
            setInterval(createSparkle, 5000);
            setTimeout(() => setInterval(createSparkle, 7000), 2000);
        });

        // Add dynamic animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes dynamicFloat {
                0% {
                    transform: translateY(0px) translateX(0px) scale(0) rotate(0deg);
                    opacity: 0;
                }
                10% {
                    opacity: 0.8;
                    transform: scale(1) rotate(0deg);
                }
                25% {
                    transform: translateY(-30px) translateX(20px) scale(1.2) rotate(90deg);
                    opacity: 0.9;
                }
                50% {
                    transform: translateY(-60px) translateX(-15px) scale(0.8) rotate(180deg);
                    opacity: 0.7;
                }
                75% {
                    transform: translateY(-90px) translateX(25px) scale(1.1) rotate(270deg);
                    opacity: 0.5;
                }
                90% {
                    opacity: 0.3;
                    transform: translateY(-120px) translateX(35px) scale(0.6) rotate(360deg);
                }
                100% {
                    transform: translateY(-150px) translateX(50px) scale(0) rotate(360deg);
                    opacity: 0;
                }
            }

            @keyframes sparkle {
                0%, 100% {
                    transform: scale(0) rotate(0deg);
                    opacity: 0;
                }
                50% {
                    transform: scale(1) rotate(180deg);
                    opacity: 1;
                }
            }

            @keyframes testPulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.3; }
            }

            /* Ensure particles are visible and animated */
            .particle {
                z-index: 1;
                animation-fill-mode: both;
            }

            .dynamic-particle {
                z-index: 2;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>
