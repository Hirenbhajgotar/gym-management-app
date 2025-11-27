<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
        <!-- Sidebar Navigation -->
        <div class="lg:col-span-1">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Settings</h2>
                <flux:navlist>
                    <flux:navlist.item :href="route('settings.profile')" wire:navigate>{{ __('Profile') }}</flux:navlist.item>
                    <flux:navlist.item :href="route('settings.password')" wire:navigate>{{ __('Password') }}</flux:navlist.item>
                    <flux:navlist.item :href="route('settings.appearance')" wire:navigate>{{ __('Appearance') }}</flux:navlist.item>
                </flux:navlist>
            </div>
        </div>

        <!-- Main Content -->
        <div class="lg:col-span-3">
            <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-8">
                <div class="mb-8">
                    <flux:heading size="lg" class="text-2xl">{{ $heading ?? '' }}</flux:heading>
                    <flux:subheading class="mt-2 text-gray-600 dark:text-gray-400">{{ $subheading ?? '' }}</flux:subheading>
                </div>

                <div class="space-y-8">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </div>
</div>
