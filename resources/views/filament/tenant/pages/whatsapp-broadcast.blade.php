<x-filament-panels::page>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left Column: Broadcast Form -->
        <div class="lg:col-span-2">
            <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden">
                <div class="p-6">
                    <div class="mb-5">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-heroicon-o-paper-airplane class="w-5 h-5 text-primary-600 dark:text-primary-400"/>
                            Compose Broadcast
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Configure your message recipients and content.</p>
                    </div>
                    
                    {{ $this->form }}
                </div>
            </div>
        </div>

        <!-- Right Column: Live Preview -->
        <div class="lg:col-span-1">
            <div class="sticky top-20">
                <div class="bg-white dark:bg-gray-900 rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 overflow-hidden h-full flex flex-col">
                    <div class="p-4 border-b border-gray-100 dark:border-white/10 bg-gray-50 dark:bg-white/5">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white flex items-center justify-between">
                            <span>Live Preview</span>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-500/20 dark:text-green-400">WhatsApp</span>
                        </h3>
                    </div>
                    
                    <div class="p-6 bg-[#E5DDD5] dark:bg-[#0b141a] min-h-[400px] flex flex-col relative bg-opacity-90">
                        <!-- WhatsApp Wallpaper Pattern Overlay (Optional) -->
                        <div class="absolute inset-0 opacity-10 pointer-events-none" 
                             style="background-image: url('https://user-images.githubusercontent.com/15075759/28719144-86dc0f70-73b1-11e7-911d-60d70fcded21.png'); background-repeat: repeat;">
                        </div>

                        <!-- Message Bubble -->
                        @if(!empty($selectedTemplate))
                            <div class="relative z-10 self-start max-w-[90%] bg-white dark:bg-[#202c33] rounded-lg shadow-sm p-3 mb-2 rounded-tl-none">
                                <div class="text-sm text-gray-900 dark:text-gray-700 whitespace-pre-wrap leading-relaxed">
                                    {{ $selectedTemplate['formatted_body'] ?? '' }}
                                </div>
                                <div class="text-[10px] text-gray-500 dark:text-gray-400 text-right mt-1 flex justify-end items-center gap-1">
                                    {{ now()->format('H:i') }}
                                    <span class="text-blue-500">
                                        <svg viewBox="0 0 16 15" width="16" height="15" class="w-3 h-3 fill-current"><path d="M15.01 3.316l-.478-.372a.365.365 0 0 0-.51.063L8.666 9.879a.32.32 0 0 1-.484.033l-.358-.325a.319.319 0 0 0-.484.032l-.378.483a.418.418 0 0 0 .036.541l1.32 1.266c.143.14.361.125.484-.033l6.272-8.048a.366.366 0 0 0-.064-.512zm-4.1 0l-.478-.372a.365.365 0 0 0-.51.063L4.566 9.879a.32.32 0 0 1-.484.033L1.891 7.769a.366.366 0 0 0-.515.006l-.423.433a.364.364 0 0 0 .006.514l3.258 3.185c.143.14.361.125.484-.033l6.272-8.048a.365.365 0 0 0-.063-.51z"></path></svg>
                                    </span>
                                </div>
                            </div>

                            <!-- Variable Placeholders Instructions -->
                            @if(!empty($selectedTemplate['variable_placeholder']))
                                <div class="relative z-10 mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700/50 rounded-md">
                                    <p class="text-xs text-gray-800 dark:text-gray-600 font-medium mb-2">Template Variables:</p>
                                    <ul class="text-xs space-y-2">
                                        @foreach($selectedTemplate['variable_placeholder'] as $varName => $varData)
                                            @php
                                                $source = $varData['source'] ?? 'custom';
                                                $isAuto = $source !== 'custom';
                                            @endphp
                                            <li class="flex flex-col gap-1 p-2 rounded {{ $isAuto ? 'bg-emerald-100/50 dark:bg-emerald-800/20' : 'bg-yellow-100/50 dark:bg-yellow-800/20' }}">
                                                <div class="flex items-center justify-between">
                                                    <span class="font-medium {{ $isAuto ? 'text-emerald-700 dark:text-emerald-400' : 'text-primary-700 dark:text-primary-300' }}">
                                                        @{{{{ $varName }}}} {{ $varData['placeholder'] ?? 'Variable ' . $varName }}
                                                    </span>
                                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ $isAuto ? 'bg-emerald-200 text-emerald-700 dark:bg-emerald-700/30 dark:text-emerald-400' : 'bg-amber-200 text-amber-700 dark:bg-amber-700/30 dark:text-amber-400' }}">
                                                        {{ $isAuto ? 'Auto' : 'Manual' }}
                                                    </span>
                                                </div>
                                                <div class="flex items-center gap-2 text-[10px] text-gray-500 dark:text-gray-400 ml-1">
                                                    @if($isAuto)
                                                        <span>Source: {{ $source }}</span>
                                                    @endif
                                                    @if(isset($varData['example']) && !empty($varData['example']))
                                                        <span>{{ $isAuto ? '|' : '' }} e.g. <span class="italic">{{ $varData['example'] }}</span></span>
                                                    @endif
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @else
                            <div class="relative z-10 flex items-center justify-center flex-1 h-full">
                                <div class="text-center p-6 bg-white/80 dark:bg-[#202c33]/80 backdrop-blur-sm rounded-xl shadow-sm">
                                    <x-heroicon-o-chat-bubble-left-ellipsis class="w-12 h-12 text-gray-400 mx-auto mb-3"/>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Select a template to preview how it will look to your members.</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('livewire:init', () => {
                Livewire.on('broadcast-sent', (event) => {
                    // You can add confetti or other success animations here
                });
            });
        </script>
    @endpush
</x-filament-panels::page>
