<x-filament-panels::page>
    <div class="space-y-6">
        @if (!$this->hasExistingAccount())
            <div class="bg-blue-50 dark:bg-gray-800 border border-blue-200 dark:border-gray-200 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800">
                            Automated WhatsApp Setup
                        </h3>
                        <div class="mt-2 text-sm text-blue-700">
                            <p>We handle all the technical setup for you! No need to create Twilio accounts or manage
                                API keys. Just provide your WhatsApp Business number and we'll do the rest.</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($this->hasExistingAccount() && $this->accountStatus['verification_status'] === 'verified')
            <div class="bg-green-50 dark:bg-gray-800 border border-green-200 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"></path>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">
                            WhatsApp Integration Active
                        </h3>
                        <div class="mt-2 text-sm text-green-700">
                            <p>Your WhatsApp Business integration is working perfectly! You can now send notifications
                                to your gym members.</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if ($this->hasExistingAccount() && $this->accountStatus['verification_status'] === 'pending')
            <div class="bg-orange-50 dark:bg-gray-800 border border-orange-200 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                clip-rule="evenodd"></path>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-orange-800">
                            WhatsApp Verification Pending
                        </h3>
                        <div class="mt-2 text-sm text-orange-700">
                            <p>A verification code has been sent to your WhatsApp Business number. Please enter it below
                                to activate your integration.</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <form wire:submit.prevent="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-between">
                <div class="space-x-3">
                    @if (!$this->hasExistingAccount())
                        <x-filament::button type="submit" icon="heroicon-m-plus-circle" color="success" size="xl"
                            tooltip="Create WhatsApp Account" wire:target="save" wire:loading.attr="disabled" outlined>
                            Create WhatsApp Account
                        </x-filament::button>
                    @endif

                    @if ($this->needsVerification())
                        <x-filament::button type="submit" icon="heroicon-o-check" color="primary" size="xl"
                            tooltip="Verify WhatsApp Number" wire:target="save" wire:loading.attr="disabled" outlined>
                            Verify Number
                        </x-filament::button>
                    @endif

                    @if ($this->hasExistingAccount() && $this->accountStatus['verification_status'] === 'verified')
                        <x-filament::button type="submit" icon="heroicon-o-paper-airplane" color="info"
                            size="xl" tooltip="Send Test Message" wire:target="save" wire:loading.attr="disabled"
                            outlined>
                            Send Test Message
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </form>

        @if ($this->hasExistingAccount())
            <div class="mt-8 bg-gray-50 dark:bg-gray-800 rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Account Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="bg-white dark:bg-gray-700 p-4 rounded border dark:border-gray-600">
                        <div class="text-2xl font-bold text-purple-600">
                            {{ ucfirst($this->accountStatus['verification_status'] ?? 'Unknown') }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-200">Verification Status</div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
