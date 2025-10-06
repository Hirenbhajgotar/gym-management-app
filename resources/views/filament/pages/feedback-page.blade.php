{{-- resources/views/filament/pages/feedback-page.blade.php --}}
<x-filament-panels::page>
    <div class="text-center py-12">
        <div class="max-w-md mx-auto">
            <div class="mb-6">
                <div
                    class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-blue-100 dark:bg-blue-900">
                    <svg class="h-8 w-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                        </path>
                    </svg>
                </div>
            </div>

            <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                Share Your Feedback
            </h2>

            <p class="text-gray-600 dark:text-gray-400 mb-6">
                Your feedback helps us improve the platform. The feedback form should have opened automatically.
            </p>

            <button onclick="window.dispatchEvent(new CustomEvent('open-feedback-modal'))"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z">
                    </path>
                </svg>
                Open Feedback Form
            </button>
        </div>
    </div>
</x-filament-panels::page>
