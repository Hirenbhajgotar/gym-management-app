@php
    $config = match($verificationStatus) {
        \App\Enums\WhatsAppAccountVerificationStatusEnum::VERIFIED => [
            'icon' => 'check',
            'title' => 'WhatsApp Business Connected And Verified',
            'message' => 'Your WhatsApp Business Account is connected and verified. You can now send messages to your members.',
            'bg' => 'bg-green-50 dark:bg-green-900/30',
            'border' => 'border-green-200 dark:border-green-700',
            'iconBg' => 'bg-green-100 dark:bg-green-800/50',
            'iconColor' => 'text-green-600 dark:text-green-400',
            'titleColor' => 'text-green-800 dark:text-green-300',
            'textColor' => 'text-green-600 dark:text-green-400',
            'iconSvg' => new Illuminate\Support\HtmlString('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>'),
        ],
        \App\Enums\WhatsAppAccountVerificationStatusEnum::IN_REVIEW => [
            'icon' => 'clock',
            'title' => 'Verification In Review',
            'message' => 'Your WhatsApp Business Account is connected and under review by Meta. This typically takes 24–48 hours. You can continue configuring other settings while waiting.',
            'bg' => 'bg-blue-50 dark:bg-blue-900/30',
            'border' => 'border-blue-200 dark:border-blue-700',
            'iconBg' => 'bg-blue-100 dark:bg-blue-800/50',
            'iconColor' => 'text-blue-600 dark:text-blue-400',
            'titleColor' => 'text-blue-800 dark:text-blue-300',
            'textColor' => 'text-blue-600 dark:text-blue-400',
            'iconSvg' => new Illuminate\Support\HtmlString('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>'),
        ],
        \App\Enums\WhatsAppAccountVerificationStatusEnum::PENDING => [
            'icon' => 'pause',
            'title' => 'Verification Pending',
            'message' => 'Your WhatsApp Business Account is connected but verification is still pending. Some messaging features may be limited until the verification completes.',
            'bg' => 'bg-amber-50 dark:bg-amber-900/30',
            'border' => 'border-amber-200 dark:border-amber-700',
            'iconBg' => 'bg-amber-100 dark:bg-amber-800/50',
            'iconColor' => 'text-amber-600 dark:text-amber-400',
            'titleColor' => 'text-amber-800 dark:text-amber-300',
            'textColor' => 'text-amber-600 dark:text-amber-400',
            'iconSvg' => new Illuminate\Support\HtmlString('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>'),
        ],
        \App\Enums\WhatsAppAccountVerificationStatusEnum::NOT_VERIFIED => [
            'icon' => 'warning',
            'title' => 'Not Verified',
            'message' => 'Your WhatsApp Business Account is connected but not yet verified. Complete the verification process through Meta to unlock all messaging features.',
            'bg' => 'bg-orange-50 dark:bg-orange-900/30',
            'border' => 'border-orange-200 dark:border-orange-700',
            'iconBg' => 'bg-orange-100 dark:bg-orange-800/50',
            'iconColor' => 'text-orange-600 dark:text-orange-400',
            'titleColor' => 'text-orange-800 dark:text-orange-300',
            'textColor' => 'text-orange-600 dark:text-orange-400',
            'iconSvg' => new Illuminate\Support\HtmlString('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"></path>'),
        ],
        \App\Enums\WhatsAppAccountVerificationStatusEnum::REJECTED => [
            'icon' => 'x',
            'title' => 'Verification Rejected',
            'message' => 'Your WhatsApp Business Account verification was rejected. Please review Meta\'s business verification requirements, update your information, and resubmit for verification.',
            'bg' => 'bg-red-50 dark:bg-red-900/30',
            'border' => 'border-red-200 dark:border-red-700',
            'iconBg' => 'bg-red-100 dark:bg-red-800/50',
            'iconColor' => 'text-red-600 dark:text-red-400',
            'titleColor' => 'text-red-800 dark:text-red-300',
            'textColor' => 'text-red-600 dark:text-red-400',
            'iconSvg' => new Illuminate\Support\HtmlString('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>'),
        ],
        default => [
            'icon' => 'check',
            'title' => 'WhatsApp Business Connected',
            'message' => 'Your WhatsApp Business Account is connected.',
            'bg' => 'bg-gray-50 dark:bg-gray-800/50',
            'border' => 'border-gray-200 dark:border-gray-700',
            'iconBg' => 'bg-gray-100 dark:bg-gray-700/50',
            'iconColor' => 'text-gray-600 dark:text-gray-400',
            'titleColor' => 'text-gray-800 dark:text-gray-300',
            'textColor' => 'text-gray-600 dark:text-gray-400',
            'iconSvg' => new Illuminate\Support\HtmlString('<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>'),
        ],
    };
@endphp

<div class="flex flex-col items-center justify-center py-8 px-4 text-center">
    <div class="flex-shrink-0 w-20 h-20 mb-4 rounded-full {{ $config['iconBg'] }} flex items-center justify-center">
        <svg class="w-10 h-10 {{ $config['iconColor'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">{{ $config['iconSvg'] }}</svg>
    </div>
    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ $config['title'] }}</h3>
    <p class="text-sm {{ $config['textColor'] }} mt-1">{{ $config['message'] }}</p>
</div>