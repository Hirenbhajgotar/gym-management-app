<x-filament-panels::page>
    <div class="space-y-6">
        @if (!$this->hasExistingAccount())
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
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
            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clip-rule="evenodd"></path>
                        </svg>
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

        <form wire:submit.prevent="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-between">
                <div></div>
                <div class="space-x-3">
                    {{ $this->getFormActions() }}
                </div>
            </div>
        </form>

        @if ($this->hasExistingAccount())
            <div class="mt-8 bg-gray-50 rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Account Information</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @if (isset($this->accountStatus['balance']))
                        <div class="bg-white p-4 rounded border">
                            <div class="text-2xl font-bold text-blue-600">
                                ${{ number_format($this->accountStatus['balance'], 2) }}</div>
                            <div class="text-sm text-gray-600">Account Balance</div>
                        </div>
                    @endif

                    @if (isset($this->accountStatus['messages_sent']))
                        <div class="bg-white p-4 rounded border">
                            <div class="text-2xl font-bold text-green-600">{{ $this->accountStatus['messages_sent'] }}
                            </div>
                            <div class="text-sm text-gray-600">Messages This Month</div>
                        </div>
                    @endif

                    @if (isset($this->accountStatus['monthly_cost']))
                        <div class="bg-white p-4 rounded border">
                            <div class="text-2xl font-bold text-orange-600">
                                ${{ number_format($this->accountStatus['monthly_cost'], 2) }}</div>
                            <div class="text-sm text-gray-600">Monthly Cost</div>
                        </div>
                    @endif

                    <div class="bg-white p-4 rounded border">
                        <div class="text-2xl font-bold text-purple-600">
                            {{ ucfirst($this->accountStatus['verification_status'] ?? 'Unknown') }}</div>
                        <div class="text-sm text-gray-600">Verification Status</div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
