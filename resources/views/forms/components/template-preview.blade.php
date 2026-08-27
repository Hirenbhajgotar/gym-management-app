@php
    $data = $getPreviewData();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div class="w-full max-w-sm mx-auto rounded-2xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden transition-all duration-200">
        <!-- WhatsApp Screen / Wallpaper Area (420px height, justify-start for top-aligned messages) -->
        <div class="p-4 sm:p-4.5 bg-[#efeae2] dark:bg-[#0b141a] relative flex flex-col justify-start items-start overflow-hidden" style="min-height: 420px;">
            <!-- WhatsApp Doodle Wallpaper Overlay (Local & Network Fallback) -->
            <div class="absolute inset-0 opacity-[0.16] dark:opacity-[0.08] pointer-events-none bg-repeat bg-center"
                 style="background-image: url('{{ asset('images/whatsapp-bg.png') }}'), url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-size: 400px;">
            </div>

            @if (! empty($data['has_template']))
                <!-- WhatsApp Message Bubble (Top Aligned in Mobile Chat Screen) -->
                <div class="relative z-10 self-start w-full max-w-[92%] sm:max-w-[88%] bg-white dark:bg-[#202c33] rounded-lg rounded-tl-none px-3.5 py-3 shadow-[0_1px_0.5px_rgba(11,20,26,0.13)] border border-black/5 dark:border-white/5">
                    <!-- Message Body Text -->
                    <div class="text-xs sm:text-sm leading-[1.5] text-[#111b21] dark:text-gray-600 whitespace-pre-wrap font-normal break-words">
                        {{ $data['resolved_body'] }}
                    </div>

                    <!-- Timestamp & Blue Double Tick Icon -->
                    <div class="flex items-center justify-end gap-1 mt-1.5 text-xs text-[#667781] dark:text-gray-400 font-light">
                        <span>{{ now()->format('h:i A') }}</span>
                        <svg class="w-3.5 h-3.5 text-[#53bdeb] fill-current" viewBox="0 0 16 15">
                            <path d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.879a.32.32 0 0 1-.484.033l-.358-.325a.319.319 0 0 0-.484.032l-.378.483a.418.418 0 0 0 .036.541l1.32 1.266c.143.14.361.125.484-.033l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.879a.32.32 0 0 1-.484.033L1.891 7.769a.366.366 0 0 0-.515.006l-.423.433a.364.364 0 0 0 .006.514l3.258 3.185c.143.14.361.125.484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"/>
                        </svg>
                    </div>
                </div>
            @else
                <!-- Empty State Card (Vertically & Horizontally Centered inside Chat Screen) -->
                <div class="relative z-10 w-full max-w-[88%] bg-white/90 dark:bg-[#202c33]/90 backdrop-blur-xs rounded-xl p-5 shadow-sm border border-black/5 dark:border-white/5 text-center my-auto mx-auto self-center">
                    <p class="text-sm text-gray-600 dark:text-gray-600 leading-snug">
                        Select a template to preview how it will look to your members.
                    </p>
                </div>
            @endif
        </div>
    </div>
</x-dynamic-component>
