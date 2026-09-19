<!-- 2. Gym QR Code Preview Modal (First-Party Server-Generated SVG QR) -->
<div x-show="showQrModal" 
    x-cloak
    role="dialog"
    aria-modal="true"
    aria-labelledby="qr-modal-title"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0">
    
    <!-- Dark Blur Backdrop -->
    <div class="fixed inset-0 bg-black/80 backdrop-blur-md transition-opacity"
        @click="showQrModal = false"></div>

    <!-- Modal Dialog Container -->
    <div class="relative w-full max-w-md bg-[#0d1520] border border-white/15 rounded-3xl shadow-[0_0_60px_rgba(0,0,0,0.9)] overflow-hidden z-10 my-8 text-center"
        x-show="showQrModal"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4"
        @click.stop>
        
        <!-- Modal Header -->
        <div class="p-6 border-b border-white/10 flex items-center justify-between">
            <div class="flex items-center gap-2.5">
                <span class="material-symbols-outlined text-cyan-400 text-xl">qr_code_scanner</span>
                <h3 id="qr-modal-title" class="font-headline font-bold text-lg text-white uppercase tracking-tight" x-text="(activeQrGym.name || 'Gym') + ' Attendance QR'"></h3>
            </div>
            <button @click="showQrModal = false" type="button"
                class="p-2 rounded-xl text-gray-400 hover:text-white bg-surface-container-low hover:bg-surface-container-high border border-white/10 transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <!-- Modal Content (First-Party Rendered SVG QR Graphic & Direct Link) -->
        <div class="p-6 sm:p-8 space-y-6">
            <!-- First-Party Vector QR Container -->
            <div class="bg-white p-6 rounded-2xl inline-block shadow-[0_0_30px_rgba(6,182,212,0.2)]">
                <div x-show="activeQrGym.qrSvg" x-html="activeQrGym.qrSvg" class="w-48 h-48 sm:w-56 sm:h-56 mx-auto flex items-center justify-center [&>svg]:w-full [&>svg]:h-full [&>svg]:object-contain"></div>
                <div x-show="!activeQrGym.qrSvg" class="w-48 h-48 sm:w-56 sm:h-56 mx-auto flex items-center justify-center text-gray-400 text-xs font-mono">
                    Generating secure QR...
                </div>
            </div>

            <div class="space-y-2">
                <p class="text-xs text-gray-300 font-medium">Display this QR code at your front desk or turnstile gate for instant member check-ins.</p>
            </div>

            <!-- Copy Link & Close Actions -->
            <div class="flex items-center gap-3">
                <button type="button"
                    @click="copyToClipboard(activeQrGym.checkinUrl, activeQrGym.id)"
                    class="flex-1 inline-flex items-center justify-center gap-2 py-3 px-4 bg-gradient-to-r from-cyan-400 to-emerald-400 text-black font-headline font-black text-xs rounded-xl shadow-[0_0_15px_rgba(6,182,212,0.3)] uppercase tracking-wider cursor-pointer">
                    <span class="material-symbols-outlined text-base" x-text="copiedGymId === activeQrGym.id ? 'check_circle' : 'link'"></span>
                    <span x-text="copiedGymId === activeQrGym.id ? 'Check-in Link Copied!' : 'Copy Check-in URL'"></span>
                </button>
                <button @click="showQrModal = false" type="button"
                    class="px-5 py-3 rounded-xl text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-white bg-surface-container-low hover:bg-surface-container-high border border-white/10 transition-colors cursor-pointer">
                    Done
                </button>
            </div>
        </div>
    </div>
</div>
