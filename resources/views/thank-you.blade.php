<x-layouts.app :title="'Thank You'">
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                <div class="space-y-6">
                    <div class="flex justify-center mb-4">
                        @if (session('email'))
                            <div class="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center">
                                <svg class="w-10 h-10 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                        @else
                            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                        @endif
                    </div>

                    @if (session('email'))
                        <h2 class="text-3xl font-bold text-gray-900 mb-2">Verify Your Email</h2>
                        <p class="text-xl text-gray-600 mb-4">
                            We've sent a verification link to<br>
                            <span class="font-bold text-blue-600">{{ session('email') }}</span>
                        </p>
                        <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 max-w-2xl mx-auto text-left">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-700">
                                        You <strong>must</strong> verify your email address to access your dashboard.
                                        If you don't see the email, please check your spam or junk folder.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Thank You for Registering!</h2>
                        <p class="text-gray-600 mb-4">
                            {{ session('success') ?? 'Your registration has been successfully submitted.' }}</p>
                    @endif

                    <div class="mt-8 flex justify-center gap-4">
                        <x-filament::button href="{{ route('filament.admin.auth.login') }}" tag="a" color="primary">
                            Go to Login
                        </x-filament::button>
                        <x-filament::button href="{{ route('home') }}" tag="a" color="gray">
                            Back to Home
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
