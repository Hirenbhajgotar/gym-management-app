<div class="text-center py-8">
    @if($whatsappAccount && $whatsappAccount->isActive() && $whatsappAccount->isPendingReview())
        <div class="flex justify-center mb-6">
            <svg width="192" height="192" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                <style>
                    .amb-blob { fill: #fef3c7; }
                    .amb-doc-back { fill: #ffffff; stroke: #fde68a; }
                    .amb-doc-front { fill: #ffffff; stroke: #f59e0b; }
                    .amb-doc-lines { stroke: #fde68a; }
                    .amb-clock { fill: #ffffff; stroke: #d97706; }
                    .amb-clock-hands { stroke: #d97706; }
                    .amb-sparkle { fill: #fcd34d; }
                    .amb-dot { fill: #fbbf24; }
                    @media (prefers-color-scheme: dark) {
                        .amb-blob { fill: rgba(120, 53, 15, 0.2); }
                        .amb-doc-back { fill: #1f2937; stroke: #92400e; }
                        .amb-doc-front { fill: #1f2937; stroke: #fbbf24; }
                        .amb-doc-lines { stroke: #b45309; }
                        .amb-clock { fill: #111827; stroke: #fbbf24; }
                        .amb-clock-hands { stroke: #fbbf24; }
                        .amb-sparkle { fill: #d97706; }
                        .amb-dot { fill: #f59e0b; }
                    }
                </style>
                <!-- Background blob -->
                <path class="amb-blob" d="M150.3 54.1c15.2 13 25.1 33.6 23.3 52.8-1.8 19.3-15.3 37.1-32.9 45.4-17.6 8.2-39.3 6.9-57.5-1.9-18.2-8.7-32.9-24.9-40-43.2-7.1-18.3-6.6-38.7 1.8-56.1 8.5-17.4 24.8-31.9 44.2-37.1 19.4-5.2 41.9-1.1 61.1 10.1z" />
                
                <!-- Document back -->
                <rect class="amb-doc-back" x="65" y="45" width="60" height="80" rx="4" transform="rotate(-8 65 45)" stroke-width="4"/>
                
                <!-- Document front -->
                <rect class="amb-doc-front" x="75" y="55" width="60" height="80" rx="4" stroke-width="4"/>
                <path class="amb-doc-lines" d="M90 75h30M90 90h30M90 105h15" stroke-width="4" stroke-linecap="round"/>
                
                <!-- Clock overlay -->
                <circle class="amb-clock" cx="125" cy="125" r="28" stroke-width="4"/>
                <path class="amb-clock-hands" d="M125 110v15l10 10" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                
                <!-- Decorations -->
                <path class="amb-sparkle" d="M45 90l3-6 6-3-6-3-3-6-3 6-6 3 6 3 3 6z" />
                <circle class="amb-dot" cx="155" cy="70" r="4" />
            </svg>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">WhatsApp Business Account Under Verification</h3>
        <p class="text-gray-500 dark:text-gray-400 mb-4 max-w-md mx-auto">Your WhatsApp Business Account is currently under review. Please wait for approval from Meta before you can start sending messages.</p>
    @else
        <div class="flex justify-center mb-6">
            <svg width="192" height="192" viewBox="0 0 200 200" fill="none" xmlns="http://www.w3.org/2000/svg">
                <style>
                    .red-blob { fill: #fee2e2; }
                    .red-doc-back { fill: #ffffff; stroke: #fecaca; }
                    .red-doc-front { fill: #ffffff; stroke: #ef4444; }
                    .red-doc-lines { stroke: #fecaca; }
                    .red-error { fill: #ffffff; stroke: #dc2626; }
                    .red-error-mark { stroke: #dc2626; }
                    .red-error-dot { fill: #dc2626; }
                    .red-sparkle { fill: #fca5a5; }
                    .red-dot { fill: #f87171; }
                    @media (prefers-color-scheme: dark) {
                        .red-blob { fill: rgba(127, 29, 29, 0.2); }
                        .red-doc-back { fill: #1f2937; stroke: #991b1b; }
                        .red-doc-front { fill: #1f2937; stroke: #f87171; }
                        .red-doc-lines { stroke: #b91c1c; }
                        .red-error { fill: #111827; stroke: #f87171; }
                        .red-error-mark { stroke: #f87171; }
                        .red-error-dot { fill: #f87171; }
                        .red-sparkle { fill: #dc2626; }
                        .red-dot { fill: #ef4444; }
                    }
                </style>
                <!-- Background blob -->
                <path class="red-blob" d="M150.3 54.1c15.2 13 25.1 33.6 23.3 52.8-1.8 19.3-15.3 37.1-32.9 45.4-17.6 8.2-39.3 6.9-57.5-1.9-18.2-8.7-32.9-24.9-40-43.2-7.1-18.3-6.6-38.7 1.8-56.1 8.5-17.4 24.8-31.9 44.2-37.1 19.4-5.2 41.9-1.1 61.1 10.1z" />
                
                <!-- Document back -->
                <rect class="red-doc-back" x="65" y="45" width="60" height="80" rx="4" transform="rotate(-8 65 45)" stroke-width="4"/>
                
                <!-- Document front -->
                <rect class="red-doc-front" x="75" y="55" width="60" height="80" rx="4" stroke-width="4"/>
                <path class="red-doc-lines" d="M90 75h30M90 90h30M90 105h15" stroke-width="4" stroke-linecap="round"/>
                
                <!-- Error overlay -->
                <circle class="red-error" cx="125" cy="125" r="28" stroke-width="4"/>
                <path class="red-error-mark" d="M125 107v20" stroke-width="4" stroke-linecap="round"/>
                <circle class="red-error-dot" cx="125" cy="138" r="3" />
                
                <!-- Decorations -->
                <path class="red-sparkle" d="M45 90l3-6 6-3-6-3-3-6-3 6-6 3 6 3 3 6z" />
                <circle class="red-dot" cx="155" cy="70" r="4" />
            </svg>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2">Setup Incomplete</h3>
        <p class="text-gray-500 dark:text-gray-400 mb-4 max-w-md mx-auto">We couldn't complete the WhatsApp setup process. Please contact support or try setting up again.</p>
    @endif
</div>