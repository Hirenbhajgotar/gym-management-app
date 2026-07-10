<div x-data="{
    showError: @entangle('showError'),
    showSuccess: @entangle('showSuccess'),
    init() {
        if (this.showError) {
            setTimeout(() => this.showError = false, 3000);
        }
        if (this.showSuccess) {
            setTimeout(() => this.showSuccess = false, 3000);
        }
    }
}">
    <!-- Error Toast -->
    @if($error)
        <div
            x-show="showError"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-x-full"
            x-transition:enter-end="opacity-100 transform translate-x-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform translate-x-0"
            x-transition:leave-end="opacity-0 transform translate-x-full"
            class="fixed top-24 right-6 z-50 bg-red-500/20 border border-red-500/30 text-red-400 px-6 py-4 rounded-lg backdrop-blur-sm max-w-sm"
        >
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    {{ is_string($error) ? $error : '' }}
                </div>
                <button
                    @click="showError = false"
                    class="ml-4 text-red-400 hover:text-red-300 transition-colors duration-200"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    @endif

    <!-- Success Toast -->
    @if($success)
        <div
            x-show="showSuccess"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 transform translate-x-full"
            x-transition:enter-end="opacity-100 transform translate-x-0"
            x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100 transform translate-x-0"
            x-transition:leave-end="opacity-0 transform translate-x-full"
            class="fixed top-24 right-6 z-50 bg-green-500/20 border border-green-500/30 text-green-400 px-6 py-4 rounded-lg backdrop-blur-sm max-w-sm"
        >
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    {{ is_string($success) ? $success : '' }}
                </div>
                <button
                    @click="showSuccess = false"
                    class="ml-4 text-green-400 hover:text-green-300 transition-colors duration-200"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
    @endif
</div>
