<div class="mt-4 p-4 bg-primary-500 dark:bg-primary-900/30 border border-primary-200 dark:border-primary-800 rounded-lg flex items-start gap-3">
    <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary-100 dark:bg-primary-800/50 flex items-center justify-center">
        <svg class="w-5 h-5 text-primary-600 dark:text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div class="flex-1">
        <p class="text-sm font-medium text-green-600 dark:text-green-400">Connected Successfully</p>
        <p class="text-sm text-green-500 dark:text-green-400 mt-1">{{ session('success') }}</p>
    </div>
</div>