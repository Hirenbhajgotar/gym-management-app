<div class="mt-4 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg flex items-start gap-3">
    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-red-100 dark:bg-red-800/50 flex items-center justify-center">
        <svg class="w-5 h-5 text-red-600 dark:text-red-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    </div>
    <div class="flex-1">
        <p class="text-sm font-medium text-red-800 dark:text-red-300">Connection Error</p>
        <p class="text-sm text-red-700 dark:text-gray-700 mt-1">{{ session('error') }}</p>
    </div>
</div>