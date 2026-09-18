<div x-data="{ 
        showSettingsModal: false, 
        settingsTab: 'profile',
        showQrModal: false,
        activeQrGym: { id: null, name: '', checkinUrl: '', city: '', qrSvg: '' },
        copiedGymId: null,
        copyToClipboard(text, id) {
            if (!text) return;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    this.copiedGymId = id;
                    setTimeout(() => this.copiedGymId = null, 2500);
                }).catch(() => {
                    this.fallbackCopy(text, id);
                });
            } else {
                this.fallbackCopy(text, id);
            }
        },
        fallbackCopy(text, id) {
            try {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.style.position = 'fixed';
                textArea.style.left = '-999999px';
                document.body.appendChild(textArea);
                textArea.focus();
                textArea.select();
                document.execCommand('copy');
                textArea.remove();
                this.copiedGymId = id;
                setTimeout(() => this.copiedGymId = null, 2500);
            } catch (e) {
                console.error('Fallback copy failed', e);
            }
        }
    }"
    x-on:profile-saved.window="showSettingsModal = false"
    x-on:password-saved.window="showSettingsModal = false"
    x-on:keydown.escape.window="showSettingsModal = false; showQrModal = false"
    class="relative min-h-screen pt-32 pb-20 lg:pt-40 lg:pb-28 bg-[#07090e] overflow-hidden font-body">
    <!-- Toast Messages -->
    @livewire('toast')

    <!-- Ambient Glowing Color Spots Background -->
    <div class="absolute inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute top-1/4 left-1/10 w-[500px] h-[500px] bg-cyan-500/10 blur-[140px] rounded-full animate-pulse"></div>
        <div class="absolute top-1/3 right-1/10 w-[600px] h-[600px] bg-emerald-500/10 blur-[150px] rounded-full animate-float"></div>
        <div class="absolute -top-20 left-1/2 -translate-x-1/2 w-[800px] h-[350px] bg-gradient-to-b from-cyan-900/20 via-emerald-900/10 to-transparent blur-[110px] pointer-events-none"></div>
    </div>

    <div class="container mx-auto px-6 max-w-7xl relative z-10">
        @include('livewire.frontend.profile._header')

        <!-- Main Workspace 2-Column Grid -->
        <div class="grid lg:grid-cols-12 gap-8 items-start">
            <!-- Left Column: User Profile, Registered Gyms & Helpdesk (lg:col-span-4) -->
            <div class="lg:col-span-4 space-y-6">
                @include('livewire.frontend.profile._profile_card')
                @include('livewire.frontend.profile._gyms')
            </div>

            <!-- Right Column: Subscription Status, Available Plans, and Payment Invoices (lg:col-span-8) -->
            <div class="lg:col-span-8 space-y-8">
                @include('livewire.frontend.profile._subscription')
                @include('livewire.frontend.profile._plans')
                @include('livewire.frontend.profile._payments')
            </div>
        </div>
    </div>

    <!-- Modals -->
    @include('livewire.frontend.profile._settings_modal')
    @include('livewire.frontend.profile._qr_modal')
</div>
