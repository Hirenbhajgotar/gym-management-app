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
            'imageSvg' => new Illuminate\Support\HtmlString('<svg width="192" height="192" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg"><style>.grn-blob { fill: #dcfce7; } .grn-doc-back { fill: #ffffff; stroke: #bbf7d0; } .grn-doc-front { fill: #ffffff; stroke: #22c55e; } .grn-doc-lines { stroke: #bbf7d0; } .grn-check-circle { fill: #ffffff; stroke: #16a34a; } .grn-check-lines { stroke: #16a34a; } .grn-sparkle { fill: #86efac; } .grn-dot { fill: #4ade80; } @media (prefers-color-scheme: dark) { .grn-blob { fill: rgba(20, 83, 45, 0.2); } .grn-doc-back { fill: #1f2937; stroke: #14532d; } .grn-doc-front { fill: #1f2937; stroke: #4ade80; } .grn-doc-lines { stroke: #166534; } .grn-check-circle { fill: #111827; stroke: #4ade80; } .grn-check-lines { stroke: #4ade80; } .grn-sparkle { fill: #16a34a; } .grn-dot { fill: #22c55e; } }</style><path class="grn-blob" d="M150.3 54.1c15.2 13 25.1 33.6 23.3 52.8-1.8 19.3-15.3 37.1-32.9 45.4-17.6 8.2-39.3 6.9-57.5-1.9-18.2-8.7-32.9-24.9-40-43.2-7.1-18.3-6.6-38.7 1.8-56.1 8.5-17.4 24.8-31.9 44.2-37.1 19.4-5.2 41.9-1.1 61.1 10.1z" /><rect class="grn-doc-back" x="65" y="45" width="60" height="80" rx="4" transform="rotate(-8 65 45)" stroke-width="4"/><rect class="grn-doc-front" x="75" y="55" width="60" height="80" rx="4" stroke-width="4"/><path class="grn-doc-lines" d="M90 75h30M90 90h30M90 105h15" stroke-width="4" stroke-linecap="round"/><circle class="grn-check-circle" cx="125" cy="125" r="28" stroke-width="4"/><path class="grn-check-lines" d="M113 125l8 8 16-16" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path class="grn-sparkle" d="M45 90l3-6 6-3-6-3-3-6-3 6-6 3 6 3 3 6z" /><circle class="grn-dot" cx="155" cy="70" r="4" /></svg>'),
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
            'imageSvg' => new Illuminate\Support\HtmlString('<svg width="192" height="192" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg"><style>.blu-blob { fill: #dbeafe; } .blu-doc-back { fill: #ffffff; stroke: #bfdbfe; } .blu-doc-front { fill: #ffffff; stroke: #3b82f6; } .blu-doc-lines { stroke: #bfdbfe; } .blu-clock-circle { fill: #ffffff; stroke: #2563eb; } .blu-clock-lines { stroke: #2563eb; } .blu-sparkle { fill: #93c5fd; } .blu-dot { fill: #60a5fa; } @media (prefers-color-scheme: dark) { .blu-blob { fill: rgba(30, 58, 138, 0.2); } .blu-doc-back { fill: #1f2937; stroke: #1e3a8a; } .blu-doc-front { fill: #1f2937; stroke: #60a5fa; } .blu-doc-lines { stroke: #1e40af; } .blu-clock-circle { fill: #111827; stroke: #60a5fa; } .blu-clock-lines { stroke: #60a5fa; } .blu-sparkle { fill: #2563eb; } .blu-dot { fill: #3b82f6; } }</style><path class="blu-blob" d="M150.3 54.1c15.2 13 25.1 33.6 23.3 52.8-1.8 19.3-15.3 37.1-32.9 45.4-17.6 8.2-39.3 6.9-57.5-1.9-18.2-8.7-32.9-24.9-40-43.2-7.1-18.3-6.6-38.7 1.8-56.1 8.5-17.4 24.8-31.9 44.2-37.1 19.4-5.2 41.9-1.1 61.1 10.1z" /><rect class="blu-doc-back" x="65" y="45" width="60" height="80" rx="4" transform="rotate(-8 65 45)" stroke-width="4"/><rect class="blu-doc-front" x="75" y="55" width="60" height="80" rx="4" stroke-width="4"/><path class="blu-doc-lines" d="M90 75h30M90 90h30M90 105h15" stroke-width="4" stroke-linecap="round"/><circle class="blu-clock-circle" cx="125" cy="125" r="28" stroke-width="4"/><path class="blu-clock-lines" d="M125 110v15l10 10" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path class="blu-sparkle" d="M45 90l3-6 6-3-6-3-3-6-3 6-6 3 6 3 3 6z" /><circle class="blu-dot" cx="155" cy="70" r="4" /></svg>'),
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
            'imageSvg' => new Illuminate\Support\HtmlString('<svg width="192" height="192" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg"><style>.amb-blob { fill: #fef3c7; } .amb-doc-back { fill: #ffffff; stroke: #fde68a; } .amb-doc-front { fill: #ffffff; stroke: #f59e0b; } .amb-doc-lines { stroke: #fde68a; } .amb-pause-circle { fill: #ffffff; stroke: #d97706; } .amb-pause-lines { stroke: #d97706; } .amb-sparkle { fill: #fcd34d; } .amb-dot { fill: #fbbf24; } @media (prefers-color-scheme: dark) { .amb-blob { fill: rgba(120, 53, 15, 0.2); } .amb-doc-back { fill: #1f2937; stroke: #92400e; } .amb-doc-front { fill: #1f2937; stroke: #fbbf24; } .amb-doc-lines { stroke: #b45309; } .amb-pause-circle { fill: #111827; stroke: #fbbf24; } .amb-pause-lines { stroke: #fbbf24; } .amb-sparkle { fill: #d97706; } .amb-dot { fill: #f59e0b; } }</style><path class="amb-blob" d="M150.3 54.1c15.2 13 25.1 33.6 23.3 52.8-1.8 19.3-15.3 37.1-32.9 45.4-17.6 8.2-39.3 6.9-57.5-1.9-18.2-8.7-32.9-24.9-40-43.2-7.1-18.3-6.6-38.7 1.8-56.1 8.5-17.4 24.8-31.9 44.2-37.1 19.4-5.2 41.9-1.1 61.1 10.1z" /><rect class="amb-doc-back" x="65" y="45" width="60" height="80" rx="4" transform="rotate(-8 65 45)" stroke-width="4"/><rect class="amb-doc-front" x="75" y="55" width="60" height="80" rx="4" stroke-width="4"/><path class="amb-doc-lines" d="M90 75h30M90 90h30M90 105h15" stroke-width="4" stroke-linecap="round"/><circle class="amb-pause-circle" cx="125" cy="125" r="28" stroke-width="4"/><path class="amb-pause-lines" d="M117 115v20M133 115v20" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path class="amb-sparkle" d="M45 90l3-6 6-3-6-3-3-6-3 6-6 3 6 3 3 6z" /><circle class="amb-dot" cx="155" cy="70" r="4" /></svg>'),
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
            'imageSvg' => new Illuminate\Support\HtmlString('<svg width="192" height="192" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg"><style>.org-blob { fill: #ffedd5; } .org-doc-back { fill: #ffffff; stroke: #fed7aa; } .org-doc-front { fill: #ffffff; stroke: #f97316; } .org-doc-lines { stroke: #fed7aa; } .org-warn-circle { fill: #ffffff; stroke: #ea580c; } .org-warn-lines { stroke: #ea580c; } .org-warn-dot { fill: #ea580c; } .org-sparkle { fill: #fdba74; } .org-dot { fill: #fb923c; } @media (prefers-color-scheme: dark) { .org-blob { fill: rgba(124, 45, 18, 0.2); } .org-doc-back { fill: #1f2937; stroke: #9a3412; } .org-doc-front { fill: #1f2937; stroke: #fb923c; } .org-doc-lines { stroke: #c2410c; } .org-warn-circle { fill: #111827; stroke: #fb923c; } .org-warn-lines { stroke: #fb923c; } .org-warn-dot { fill: #fb923c; } .org-sparkle { fill: #ea580c; } .org-dot { fill: #f97316; } }</style><path class="org-blob" d="M150.3 54.1c15.2 13 25.1 33.6 23.3 52.8-1.8 19.3-15.3 37.1-32.9 45.4-17.6 8.2-39.3 6.9-57.5-1.9-18.2-8.7-32.9-24.9-40-43.2-7.1-18.3-6.6-38.7 1.8-56.1 8.5-17.4 24.8-31.9 44.2-37.1 19.4-5.2 41.9-1.1 61.1 10.1z" /><rect class="org-doc-back" x="65" y="45" width="60" height="80" rx="4" transform="rotate(-8 65 45)" stroke-width="4"/><rect class="org-doc-front" x="75" y="55" width="60" height="80" rx="4" stroke-width="4"/><path class="org-doc-lines" d="M90 75h30M90 90h30M90 105h15" stroke-width="4" stroke-linecap="round"/><circle class="org-warn-circle" cx="125" cy="125" r="28" stroke-width="4"/><path class="org-warn-lines" d="M125 107v20" stroke-width="4" stroke-linecap="round"/><circle class="org-warn-dot" cx="125" cy="138" r="3" /><path class="org-sparkle" d="M45 90l3-6 6-3-6-3-3-6-3 6-6 3 6 3 3 6z" /><circle class="org-dot" cx="155" cy="70" r="4" /></svg>'),
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
            'imageSvg' => new Illuminate\Support\HtmlString('<svg width="192" height="192" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg"><style>.red-blob { fill: #fee2e2; } .red-doc-back { fill: #ffffff; stroke: #fecaca; } .red-doc-front { fill: #ffffff; stroke: #ef4444; } .red-doc-lines { stroke: #fecaca; } .red-err-circle { fill: #ffffff; stroke: #dc2626; } .red-err-lines { stroke: #dc2626; } .red-sparkle { fill: #fca5a5; } .red-dot { fill: #f87171; } @media (prefers-color-scheme: dark) { .red-blob { fill: rgba(127, 29, 29, 0.2); } .red-doc-back { fill: #1f2937; stroke: #991b1b; } .red-doc-front { fill: #1f2937; stroke: #f87171; } .red-doc-lines { stroke: #b91c1c; } .red-err-circle { fill: #111827; stroke: #f87171; } .red-err-lines { stroke: #f87171; } .red-sparkle { fill: #dc2626; } .red-dot { fill: #ef4444; } }</style><path class="red-blob" d="M150.3 54.1c15.2 13 25.1 33.6 23.3 52.8-1.8 19.3-15.3 37.1-32.9 45.4-17.6 8.2-39.3 6.9-57.5-1.9-18.2-8.7-32.9-24.9-40-43.2-7.1-18.3-6.6-38.7 1.8-56.1 8.5-17.4 24.8-31.9 44.2-37.1 19.4-5.2 41.9-1.1 61.1 10.1z" /><rect class="red-doc-back" x="65" y="45" width="60" height="80" rx="4" transform="rotate(-8 65 45)" stroke-width="4"/><rect class="red-doc-front" x="75" y="55" width="60" height="80" rx="4" stroke-width="4"/><path class="red-doc-lines" d="M90 75h30M90 90h30M90 105h15" stroke-width="4" stroke-linecap="round"/><circle class="red-err-circle" cx="125" cy="125" r="28" stroke-width="4"/><path class="red-err-lines" d="M115 115l20 20M135 115l-20 20" stroke-width="4" stroke-linecap="round"/><path class="red-sparkle" d="M45 90l3-6 6-3-6-3-3-6-3 6-6 3 6 3 3 6z" /><circle class="red-dot" cx="155" cy="70" r="4" /></svg>'),
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
            'imageSvg' => new Illuminate\Support\HtmlString('<svg width="192" height="192" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg"><style>.gry-blob { fill: #f3f4f6; } .gry-doc-back { fill: #ffffff; stroke: #e5e7eb; } .gry-doc-front { fill: #ffffff; stroke: #6b7280; } .gry-doc-lines { stroke: #e5e7eb; } .gry-chk-circle { fill: #ffffff; stroke: #4b5563; } .gry-chk-lines { stroke: #4b5563; } .gry-sparkle { fill: #d1d5db; } .gry-dot { fill: #9ca3af; } @media (prefers-color-scheme: dark) { .gry-blob { fill: rgba(31, 41, 55, 0.4); } .gry-doc-back { fill: #1f2937; stroke: #374151; } .gry-doc-front { fill: #1f2937; stroke: #9ca3af; } .gry-doc-lines { stroke: #4b5563; } .gry-chk-circle { fill: #111827; stroke: #9ca3af; } .gry-chk-lines { stroke: #9ca3af; } .gry-sparkle { fill: #6b7280; } .gry-dot { fill: #9ca3af; } }</style><path class="gry-blob" d="M150.3 54.1c15.2 13 25.1 33.6 23.3 52.8-1.8 19.3-15.3 37.1-32.9 45.4-17.6 8.2-39.3 6.9-57.5-1.9-18.2-8.7-32.9-24.9-40-43.2-7.1-18.3-6.6-38.7 1.8-56.1 8.5-17.4 24.8-31.9 44.2-37.1 19.4-5.2 41.9-1.1 61.1 10.1z" /><rect class="gry-doc-back" x="65" y="45" width="60" height="80" rx="4" transform="rotate(-8 65 45)" stroke-width="4"/><rect class="gry-doc-front" x="75" y="55" width="60" height="80" rx="4" stroke-width="4"/><path class="gry-doc-lines" d="M90 75h30M90 90h30M90 105h15" stroke-width="4" stroke-linecap="round"/><circle class="gry-chk-circle" cx="125" cy="125" r="28" stroke-width="4"/><path class="gry-chk-lines" d="M113 125l8 8 16-16" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/><path class="gry-sparkle" d="M45 90l3-6 6-3-6-3-3-6-3 6-6 3 6 3 3 6z" /><circle class="gry-dot" cx="155" cy="70" r="4" /></svg>'),
        ],
    };
@endphp

<div class="flex flex-col items-center justify-center py-8 px-4 text-center">
    @if(isset($config['imageSvg']))
        <div class="mb-6 flex justify-center">
            {{ $config['imageSvg'] }}
        </div>
    @else
        <div class="flex-shrink-0 w-20 h-20 mb-4 rounded-full {{ $config['iconBg'] }} flex items-center justify-center">
            <svg class="w-10 h-10 {{ $config['iconColor'] }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">{{ $config['iconSvg'] }}</svg>
        </div>
    @endif
    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">{{ $config['title'] }}</h3>
    <p class="text-sm {{ $config['textColor'] }} mt-1">{{ $config['message'] }}</p>
</div>