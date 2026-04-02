<x-filament-panels::page>
    <div class="space-y-6">
        @if($viewState === 'loading')
            <div class="flex items-center justify-center py-12">
                <x-filament::loading-indicator class="w-8 h-8" />
            </div>

        @elseif($viewState === 'onboarding')
            <div class="space-y-6">
                {{ $this->form }}
            </div>

        @elseif($viewState === 'setup')
            <div class="space-y-6">
                {{-- Setup Form with Wizard --}}
                {{ $this->form }}
            </div>

        @elseif($viewState === 'active')
            <div class="space-y-6">
                {{-- Active Status Banner --}}
                <div class="bg-green-50 dark:bg-gray-800 border border-green-200 dark:border-green-700 rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-semibold text-green-900 dark:text-green-400">
                                WhatsApp Integration Active
                            </h3>
                            <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                                Your WhatsApp Business integration is working perfectly. You can now send notifications to your members.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Stats Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Account Status</p>
                                <p class="mt-2 text-2xl font-semibold text-green-600 dark:text-green-400">Active</p>
                            </div>
                            <div class="p-3 bg-green-100 dark:bg-gray-700 rounded-full">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">WhatsApp Number</p>
                                <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100 truncate">
                                    {{ $phoneNumber?->phone_number ?? 'N/A' }}
                                </p>
                            </div>
                            <div class="p-3 bg-purple-100 dark:bg-gray-700 rounded-full">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Message Credits</p>
                                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $creditBalance?->remaining_credits ?? 0 }}
                                </p>
                            </div>
                            <div class="p-3 bg-blue-100 dark:bg-gray-700 rounded-full">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Active Form --}}
                {{ $this->form }}

                {{-- Quick Actions --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4">Quick Actions</h4>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('filament.admin.resources.whats-app-templates.index', ['tenant' => $this->getTenantSlug()]) }}" class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Manage Templates
                        </a>
                        <a href="{{ route('filament.admin.pages.purchase-credits', ['tenant' => $this->getTenantSlug()]) }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Purchase Credits
                        </a>
                    </div>
                </div>
            </div>

        @elseif($viewState === 'error')
            <div class="space-y-6">
                {{ $this->form }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
