<div x-data="{ 
        showSettingsModal: false, 
        settingsTab: 'profile',
        showQrModal: false,
        activeQrGym: { id: null, name: '', checkinUrl: '', city: '', qrSvg: '' },
        copiedGymId: null,
        copyToClipboard(text, id) {
            if (!text) return;
            const finish = () => {
                this.copiedGymId = id;
                setTimeout(() => this.copiedGymId = null, 2000);
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(finish).catch(() => this.fallbackCopy(text, finish));
            } else {
                this.fallbackCopy(text, finish);
            }
        },
        fallbackCopy(text, callback) {
            try {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                if (callback) callback();
            } catch (e) {
                console.error('Copy failed', e);
            }
        }
    }"
    x-on:profile-saved.window="showSettingsModal = false"
    x-on:password-saved.window="showSettingsModal = false"
    x-on:keydown.escape.window="showSettingsModal = false; showQrModal = false"
    class="relative min-h-screen pt-32 pb-20 lg:pt-40 lg:pb-28 bg-[#07090e] overflow-hidden font-body">

    <!-- Subtle Ambient Backdrop -->
    <div class="absolute inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute top-1/4 left-1/10 w-[450px] h-[450px] bg-cyan-500/5 blur-[120px] rounded-full"></div>
        <div class="absolute top-1/3 right-1/10 w-[500px] h-[500px] bg-emerald-500/5 blur-[130px] rounded-full"></div>
        <div class="absolute -top-20 left-1/2 -translate-x-1/2 w-[700px] h-[300px] bg-gradient-to-b from-cyan-900/15 via-emerald-900/5 to-transparent blur-[90px]"></div>
    </div>

    <div class="container mx-auto px-6 max-w-7xl relative z-10">
        @include('livewire.frontend.profile._header')

        <!-- Main Workspace 2-Column Grid -->
        <div class="grid lg:grid-cols-12 gap-8 items-start">
            <!-- Left Column: User Profile, Registered Gyms & Helpdesk -->
            <div class="lg:col-span-4 space-y-6">
                @include('livewire.frontend.profile._profile_card')
                @include('livewire.frontend.profile._gyms')
            </div>

            <!-- Right Column: Subscription Status, Available Plans, and Payment Invoices -->
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
