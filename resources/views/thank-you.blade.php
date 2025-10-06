<x-layouts.app :title="'Thank You'">
    <div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white rounded-xl shadow-lg p-8 text-center">
                <div class="space-y-6">
                    <div class="flex justify-center mb-4">
                        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center">
                            <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                    </div>
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Thank You for Registering!</h2>
                    <p class="text-gray-600 mb-4">Your registration has been successfully submitted. We will review your
                        application and contact you soon. In the meantime, you can explore our website.</p>
                    <div class="mt-6">
                        <x-filament::button href="{{ route('home') }}" tag="a" color="gray"
                            class="text-gray-100 !text-gray-100 bg-blue-600 !bg-blue-600 hover:bg-blue-700 !hover:bg-blue-700">
                            Go to Homepage
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
