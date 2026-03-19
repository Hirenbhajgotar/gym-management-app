<div class="flex items-center gap-4 p-4 rounded-lg {{ $configured ? 'bg-green-50 dark:bg-green-900/20' : 'bg-yellow-50 dark:bg-yellow-900/20' }}">
    @if($configured)
        <div class="flex items-center gap-2">
            <x-heroicon-o-check-circle class="w-6 h-6 text-green-500" />
            <div>
                <p class="font-medium text-green-700 dark:text-green-300">WhatsApp is configured and ready</p>
                <p class="text-sm text-green-600 dark:text-green-400">Your WhatsApp Business account is active and verified</p>
            </div>
        </div>
    @else
        <div class="flex items-center gap-2">
            <x-heroicon-o-exclamation-triangle class="w-6 h-6 text-yellow-500" />
            <div>
                <p class="font-medium text-yellow-700 dark:text-yellow-300">WhatsApp is not configured</p>
                <p class="text-sm text-yellow-600 dark:text-yellow-400">
                    <a href="{{ route('filament.admin.pages.whats-app-setup', ['tenant' => $gym->slug]) }}" class="underline">Set up your WhatsApp account</a> to enable notifications
                </p>
            </div>
        </div>
    @endif
</div>
