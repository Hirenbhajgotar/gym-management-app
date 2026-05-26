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
                {{-- Verification Status Banner --}}
                @php
                    $verificationMessage = match($verificationStatus) {
                        \App\Enums\WhatsAppAccountVerificationStatusEnum::VERIFIED => [
                            'title' => 'WhatsApp Business Verified',
                            'message' => 'Your WhatsApp Business account is fully verified. All messaging capabilities are available.',
                            'icon' => 'heroicon-o-check-circle',
                            'color' => 'text-green-600 dark:text-green-400',
                            'bg' => 'bg-green-50 dark:bg-green-900/20',
                            'border' => 'border-green-200 dark:border-green-700',
                            'text' => 'text-green-700 dark:text-green-300',
                        ],
                        \App\Enums\WhatsAppAccountVerificationStatusEnum::IN_REVIEW => [
                            'title' => 'Verification In Review',
                            'message' => 'Your WhatsApp Business account is under review by Meta. This typically takes 24-48 hours.',
                            'icon' => 'heroicon-o-clock',
                            'color' => 'text-blue-600 dark:text-gray-600',
                            'bg' => 'bg-blue-50 dark:bg-blue-900/20',
                            'border' => 'border-blue-200 dark:border-blue-700',
                            'text' => 'text-blue-700 dark:text-blue-300',
                        ],
                        \App\Enums\WhatsAppAccountVerificationStatusEnum::PENDING => [
                            'title' => 'Verification Pending',
                            'message' => 'Your WhatsApp Business account verification is pending. Some features may be limited until verification completes.',
                            'icon' => 'heroicon-o-pause',
                            'color' => 'text-amber-600 dark:text-amber-900',
                            'bg' => 'bg-amber-100 dark:bg-amber-500',
                            'border' => 'border-amber-200 dark:border-amber-500',
                            'text' => 'text-amber-700 dark:text-amber-300',
                        ],
                        \App\Enums\WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED => [
                            'title' => 'Not Verified',
                            'message' => 'Your WhatsApp Business account is not yet verified. Complete verification to unlock all features.',
                            'icon' => 'heroicon-o-exclamation-triangle',
                            'color' => 'text-orange-600 dark:text-orange-400',
                            'bg' => 'bg-orange-50 dark:bg-orange-900/20',
                            'border' => 'border-orange-200 dark:border-orange-700',
                            'text' => 'text-orange-700 dark:text-orange-300',
                        ],
                        \App\Enums\WhatsAppAccountVerificationStatusEnum::REJECTED => [
                            'title' => 'Verification Rejected',
                            'message' => 'Your WhatsApp Business account verification was rejected. Please review Meta\'s requirements and resubmit.',
                            'icon' => 'heroicon-o-x-circle',
                            'color' => 'text-red-600 dark:text-red-400',
                            'bg' => 'bg-red-50 dark:bg-red-900/20',
                            'border' => 'border-red-200 dark:border-red-700',
                            'text' => 'text-red-700 dark:text-red-300',
                        ],
                        default => [
                            'title' => 'WhatsApp Integration Active',
                            'message' => 'Your WhatsApp Business integration is working. Verification status is being updated.',
                            'icon' => 'heroicon-o-check-circle',
                            'color' => 'text-green-600 dark:text-green-400',
                            'bg' => 'bg-green-50 dark:bg-green-900/20',
                            'border' => 'border-green-200 dark:border-green-700',
                            'text' => 'text-green-700 dark:text-green-300',
                        ],
                    };
                @endphp

                <div class="{{ $verificationMessage['bg'] }} {{ $verificationMessage['border'] }} border rounded-lg p-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0">
                            <x-filament::icon :icon="$verificationMessage['icon']" class="w-6 h-6 {{ $verificationMessage['color'] }}" />
                        </div>
                        <div class="flex-1">
                            <h3 class="text-sm font-semibold {{ $verificationMessage['color'] }}">
                                {{ $verificationMessage['title'] }}
                            </h3>
                            <p class="mt-1 text-sm {{ $verificationMessage['text'] }}">
                                {{ $verificationMessage['message'] }}
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Stats Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Account Status</p>
                                <p class="mt-2 text-2xl font-semibold text-green-600 dark:text-green-400">Active</p>
                                @if($verificationStatus)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Verification: <span class="font-medium">{{ $verificationStatus->getLabel() }}</span>
                                    </p>
                                @endif
                            </div>
                            <div class="p-3 bg-green-100 dark:bg-gray-700 rounded-full">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
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

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
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
                        <a href="{{ route('filament.admin.pages.purchase-credits', ['tenant' => $this->getTenantSlug()]) }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                            Purchase Credits
                        </a>
                        <a href="{{ route('filament.admin.resources.whats-app-templates.index', ['tenant' => $this->getTenantSlug()]) }}" wire:navigate class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            Manage Templates
                        </a>
                    </div>
                </div>
            </div>

        @elseif($viewState === 'error')
            <div class="space-y-6">
                {{ $this->form }}
            </div>

        @elseif($viewState === 'account_issue')
            <div class="space-y-6">
                @php
                    $status = $whatsappAccount?->status;

                    $config = match($status) {
                        \App\Enums\WhatsAppAccountStatusEnum::BANNED => [
                            'title' => 'WhatsApp Business Account Banned',
                            'message' => 'Your WhatsApp Business account has been banned by Meta. This usually means a severe policy violation was detected. Messaging capabilities are completely disabled. Please contact Meta Business Support for an appeal, or contact support for assistance with your account.',
                            'icon' => 'heroicon-o-shield-exclamation',
                            'color' => 'text-red-600 dark:text-red-400',
                            'bg' => 'bg-red-50 dark:bg-red-900/20',
                            'border' => 'border-red-200 dark:border-red-700',
                            'text' => 'text-red-700 dark:text-red-300',
                            'alertBg' => 'bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700',
                            'alertColor' => 'text-red-800 dark:text-red-200',
                        ],
                        \App\Enums\WhatsAppAccountStatusEnum::RESTRICTED => [
                            'title' => 'WhatsApp Business Account Restricted',
                            'message' => 'Your WhatsApp Business account has been restricted by Meta. Your account is not fully operational and messaging capabilities may be limited. Please review Meta\'s business policies and address any compliance issues. Contact support if you believe this is an error.',
                            'icon' => 'heroicon-o-hand-raised',
                            'color' => 'text-orange-600 dark:text-orange-400',
                            'bg' => 'bg-orange-50 dark:bg-orange-900/20',
                            'border' => 'border-orange-200 dark:border-orange-700',
                            'text' => 'text-orange-700 dark:text-orange-300',
                            'alertBg' => 'bg-orange-100 dark:bg-orange-900/30 border border-orange-300 dark:border-orange-700',
                            'alertColor' => 'text-orange-800 dark:text-orange-200',
                        ],
                        \App\Enums\WhatsAppAccountStatusEnum::SUSPENDED => [
                            'title' => 'WhatsApp Business Account Suspended',
                            'message' => 'Your WhatsApp Business account has been suspended by Meta. This is typically a temporary state. Messaging capabilities may be limited. Please check your Meta Business Manager for suspension details and appeal if needed, or contact support for assistance.',
                            'icon' => 'heroicon-o-pause-circle',
                            'color' => 'text-orange-600 dark:text-orange-400',
                            'bg' => 'bg-orange-50 dark:bg-orange-900/20',
                            'border' => 'border-orange-200 dark:border-orange-700',
                            'text' => 'text-orange-700 dark:text-orange-300',
                            'alertBg' => 'bg-orange-100 dark:bg-orange-900/30 border border-orange-300 dark:border-orange-700',
                            'alertColor' => 'text-orange-800 dark:text-orange-200',
                        ],
                        \App\Enums\WhatsAppAccountStatusEnum::BLOCKED => [
                            'title' => 'WhatsApp Business Account Blocked',
                            'message' => 'Your WhatsApp Business account has been blocked by Meta. This means your account has been disabled and messaging is not available. You will need to contact Meta Business Support to appeal this decision. In the meantime, please reach out to our support team for alternative communication options.',
                            'icon' => 'heroicon-o-x-circle',
                            'color' => 'text-red-600 dark:text-red-400',
                            'bg' => 'bg-red-50 dark:bg-red-900/20',
                            'border' => 'border-red-200 dark:border-red-700',
                            'text' => 'text-red-700 dark:text-red-300',
                            'alertBg' => 'bg-red-100 dark:bg-red-900/30 border border-red-300 dark:border-red-700',
                            'alertColor' => 'text-red-800 dark:text-red-200',
                        ],
                        default => [
                            'title' => 'Account Status Issue',
                            'message' => 'There is an issue with your WhatsApp Business account. Please contact support.',
                            'icon' => 'heroicon-o-exclamation-triangle',
                            'color' => 'text-yellow-600 dark:text-yellow-400',
                            'bg' => 'bg-yellow-50 dark:bg-yellow-900/20',
                            'border' => 'border-yellow-200 dark:border-yellow-700',
                            'text' => 'text-yellow-700 dark:text-yellow-300',
                            'alertBg' => 'bg-yellow-100 dark:bg-yellow-900/30 border border-yellow-300 dark:border-yellow-700',
                            'alertColor' => 'text-yellow-800 dark:text-yellow-200',
                        ],
                    };
                @endphp

                {{-- Alert Banner --}}
                <div class="{{ $config['alertBg'] }} rounded-lg p-4 flex items-start gap-3">
                    <x-filament::icon :icon="$config['icon']" class="w-6 h-6 flex-shrink-0 mt-0.5 {{ $config['color'] }}" />
                    <div class="flex-1">
                        <h3 class="text-sm font-semibold {{ $config['alertColor'] }}">{{ $config['title'] }}</h3>
                        <p class="mt-1 text-sm {{ $config['text'] }}">{{ $config['message'] }}</p>
                    </div>
                </div>

                {{-- Stats Cards --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Account Status</p>
                                <p class="mt-2 text-lg font-semibold text-red-600 dark:text-red-400">{{ $status?->getLabel() ?? 'Unknown' }}</p>
                                @if($verificationStatus)
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Verification: <span class="font-medium">{{ $verificationStatus->getLabel() }}</span>
                                    </p>
                                @endif
                            </div>
                            <div class="p-3 bg-red-100 dark:bg-gray-700 rounded-full">
                                <x-filament::icon :icon="$config['icon']" class="w-6 h-6 text-red-600 dark:text-red-400" />
                            </div>
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
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

                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 border border-gray-200 dark:border-gray-700">
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
            </div>
        @endif
    </div>
</x-filament-panels::page>
