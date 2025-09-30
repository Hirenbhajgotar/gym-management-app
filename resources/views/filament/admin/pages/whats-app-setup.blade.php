<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Status Banner --}}
        @if ($viewState === 'create')
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-100">
                            Get Started with WhatsApp Notifications
                        </h3>
                        <p class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                            Connect your WhatsApp Business number to send automated notifications to your gym members.
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($viewState === 'verify')
            <div
                class="bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="currentColor"
                            viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-orange-900 dark:text-orange-100">
                            Verification Required
                        </h3>
                        <p class="mt-1 text-sm text-orange-700 dark:text-orange-300">
                            Check your WhatsApp for the verification code. The message should arrive within 1-2 minutes.
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($viewState === 'active')
            <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold text-green-900 dark:text-green-100">
                            WhatsApp Integration Active
                        </h3>
                        <p class="mt-1 text-sm text-green-700 dark:text-green-300">
                            Your WhatsApp Business integration is working perfectly. You can now send notifications to
                            your members.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Main Form --}}
        <form wire:submit.prevent="save">
            {{ $this->form }}

            {{-- Action Buttons --}}
            <div class="mt-6 flex flex-wrap items-center gap-3">
                @if ($viewState === 'create')
                    {{ ($this->createAccountAction)(['size' => 'xl']) }}

                    {{-- <x-filament::button type="submit" icon="heroicon-m-plus-circle" color="success" size="xl"
                        tooltip="Create WhatsApp Account" wire:target="save" wire:loading.attr="disabled" outlined>
                        Create WhatsApp Account
                    </x-filament::button> --}}
                @elseif ($viewState === 'verify')
                    <div class="flex flex-wrap items-center gap-3">
                        {{ ($this->verifyNumberAction)(['size' => 'xl']) }}
                        {{ ($this->resendCodeAction)(['size' => 'xl']) }}

                        {{-- <x-filament::button type="submit" icon="heroicon-o-check" color="primary" size="xl"
                            tooltip="Verify WhatsApp Number" wire:target="save" wire:loading.attr="disabled" outlined>
                            Verify Number
                        </x-filament::button> --}}
                    </div>
                @elseif ($viewState === 'active')
                    {{ ($this->testMessageAction)(['size' => 'xl']) }}

                    {{-- <x-filament::button type="submit" icon="heroicon-o-paper-airplane" color="info" size="xl"
                        tooltip="Send Test Message" wire:target="save" wire:loading.attr="disabled" outlined>
                        Send Test Message
                    </x-filament::button> --}}
                @endif
            </div>
        </form>

        {{-- Additional Info Cards for Active State --}}
        @if ($viewState === 'active' && $settings)
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="p-3">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Account Status</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ ucfirst($settings->account_status->value ?? 'N/A') }}
                            </p>
                        </div>
                        <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-full">
                            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="p-3">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Verification</p>
                            <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ ucfirst($settings->verification_status->value ?? 'N/A') }}
                            </p>
                        </div>
                        <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-full">
                            <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="p-3">
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">WhatsApp Number</p>
                            <p class="mt-2 text-lg font-semibold text-gray-900 dark:text-gray-100 truncate">
                                {{ $settings->whatsapp_business_number ?? 'N/A' }}
                            </p>
                        </div>
                        <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-full">
                            <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Help Section --}}
            <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
                <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">Need Help?</h4>
                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>Send test messages to verify your integration is working correctly</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>All costs are included in your subscription - no additional charges</span>
                    </li>
                    <li class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-gray-400 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd" />
                        </svg>
                        <span>Members receive notifications on WhatsApp automatically for bookings, payments, and
                            more</span>
                    </li>
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>
