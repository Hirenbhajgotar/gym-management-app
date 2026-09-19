<!-- Profile Information Card -->
<div class="bg-[#0d1520]/90 border border-white/10 rounded-2xl p-6 sm:p-7 shadow-[0_0_40px_rgba(0,0,0,0.6)] backdrop-blur-xl relative overflow-hidden group">
    <div class="absolute -top-12 -right-12 w-36 h-36 bg-cyan-500/10 rounded-full blur-2xl group-hover:bg-cyan-500/20 transition-all duration-500"></div>

    <!-- Card Header with User Avatar -->
    <div class="flex items-center justify-between pb-6 border-b border-white/10">
        <div class="flex items-center gap-4">
            <div class="h-16 w-16 rounded-2xl bg-gradient-to-tr from-[#0a0f18] to-emerald-950/60 border-2 border-emerald-400/40 flex items-center justify-center shadow-[0_0_20px_rgba(16,185,129,0.25)] text-2xl font-headline font-black text-emerald-300">
                {{ strtoupper(substr($this->user->name, 0, 1)) }}
            </div>
            <div class="space-y-1 overflow-hidden">
                <h3 class="font-headline font-bold text-xl text-white truncate">{{ $this->user->name }}</h3>
                <div class="flex items-center gap-2">
                    @if($this->user->status === \App\Enums\UserStatus::ACTIVE)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                            Active
                        </span>
                    @elseif($this->user->status === \App\Enums\UserStatus::UNDER_REVIEW)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/15 text-amber-400 border border-amber-500/30 uppercase tracking-wider">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                            Under Review
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500/15 text-rose-400 border border-rose-500/30 uppercase tracking-wider">
                            Inactive
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <!-- Edit Quick Icon Button -->
        <button @click="showSettingsModal = true; settingsTab = 'profile'" type="button"
            title="Edit Profile"
            class="p-2.5 rounded-xl bg-surface-container-low/80 hover:bg-surface-container-high border border-white/10 hover:border-emerald-500/40 text-gray-400 hover:text-emerald-400 transition-all cursor-pointer">
            <span class="material-symbols-outlined text-lg">edit</span>
        </button>
    </div>

    <!-- User Detail Fields -->
    <div class="py-6 space-y-4 text-sm">
        <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center flex-shrink-0 text-gray-400">
                <span class="material-symbols-outlined text-lg">mail</span>
            </div>
            <div class="overflow-hidden">
                <div class="text-[11px] font-bold uppercase tracking-widest text-gray-400">Email Address</div>
                <div class="text-white font-medium truncate">{{ $this->user->email }}</div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center flex-shrink-0 text-gray-400">
                <span class="material-symbols-outlined text-lg">call</span>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase tracking-widest text-gray-400">Phone Number</div>
                <div class="text-white font-medium">{{ $this->user->phone ?? 'Not provided' }}</div>
            </div>
        </div>

        <!-- Multi-Tenant Role Identifier -->
        <div class="flex items-center gap-3">
            <div class="h-9 w-9 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center flex-shrink-0 text-emerald-400">
                <span class="material-symbols-outlined text-lg">badge</span>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase tracking-widest text-gray-400">Account Role</div>
                <div class="text-emerald-300 font-semibold uppercase text-xs tracking-wider">
                    {{ $this->user->roles->first()?->name ?? 'Gym Owner' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Account Warnings if Under Review or Inactive -->
    @if($this->user->status === \App\Enums\UserStatus::UNDER_REVIEW)
        <div class="mb-6 p-4 bg-amber-500/10 border border-amber-500/30 rounded-xl space-y-2">
            <div class="flex items-center gap-2 text-amber-400 font-bold text-xs uppercase tracking-wider">
                <span class="material-symbols-outlined text-base">info</span>
                <span>Account Under Verification</span>
            </div>
            <p class="text-xs text-amber-200/80 leading-relaxed">
                Our administration team is reviewing your gym profile. You will receive an SMS and email notification once verified.
            </p>
        </div>
    @elseif($this->user->status === \App\Enums\UserStatus::INACTIVE)
        <div class="mb-6 p-4 bg-rose-500/10 border border-rose-500/30 rounded-xl space-y-2">
            <div class="flex items-center gap-2 text-rose-400 font-bold text-xs uppercase tracking-wider">
                <span class="material-symbols-outlined text-base">warning</span>
                <span>Account Suspended</span>
            </div>
            <p class="text-xs text-rose-200/80 leading-relaxed">
                Your account is currently inactive. Please contact support to reactivate your access.
            </p>
        </div>
    @endif

    <!-- Profile Management & Logout Action Buttons -->
    <div class="pt-4 border-t border-white/10 space-y-2.5">
        <button @click="showSettingsModal = true; settingsTab = 'profile'" type="button"
            class="w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-surface-container-high hover:bg-surface-container-highest border border-white/10 hover:border-emerald-400/40 text-white rounded-xl transition-all text-xs font-headline font-bold uppercase tracking-wider cursor-pointer">
            <span class="material-symbols-outlined text-base text-emerald-400">manage_accounts</span>
            <span>Account & Security Settings</span>
        </button>

        <button wire:click="logout" wire:loading.attr="disabled"
            class="group/logout w-full inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-[#0a0f18]/80 hover:bg-rose-500/10 border border-rose-500/30 hover:border-rose-500/60 text-rose-400 rounded-xl transition-all text-xs font-bold uppercase tracking-wider cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
            <span wire:loading.remove wire:target="logout" class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base group-hover/logout:-translate-x-0.5 transition-transform">logout</span>
                <span>Sign Out</span>
            </span>
            <span wire:loading.flex wire:target="logout" class="items-center gap-2">
                <svg class="w-4 h-4 animate-spin text-rose-400" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Signing out...</span>
            </span>
        </button>
    </div>
</div>

<!-- Dedicated Support & WhatsApp Helpdesk Card -->
<div class="bg-[#0d1520]/90 border border-white/10 rounded-2xl p-6 sm:p-7 shadow-[0_0_40px_rgba(0,0,0,0.6)] backdrop-blur-xl relative overflow-hidden group space-y-4">
    <div class="absolute -top-10 -right-10 w-32 h-32 bg-emerald-500/10 rounded-full blur-2xl group-hover:bg-emerald-500/20 transition-all duration-500"></div>

    <!-- Card Header -->
    <div class="flex items-center justify-between pb-3 border-b border-white/10">
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-[#25D366] text-2xl">support_agent</span>
            <h3 class="font-headline font-bold text-lg text-white uppercase tracking-tight">Dedicated Helpdesk</h3>
        </div>
        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold font-mono bg-emerald-500/15 text-emerald-400 border border-emerald-500/30 uppercase tracking-wider">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
            Online
        </span>
    </div>

    <!-- Brief Description -->
    <p class="text-xs text-gray-400 leading-relaxed">
        Need assistance with biometric gates, WhatsApp automation templates, or member billing? Our dedicated support team is available to assist you.
    </p>

    <!-- Support Actions -->
    <div class="space-y-3">
        @php
            $rawPhone = config('app.contact_phone', '+918469290865');
            $cleanPhone = preg_replace('/[^0-9]/', '', (string) $rawPhone);
            $gymName = $this->user->gyms->first()?->name ?? 'my gym';
            $waMessage = rawurlencode("Hi FluxFitness Support Team, I am {$this->user->name} from {$gymName} (Email: {$this->user->email}). I need assistance with my account.");
            $waUrl = "https://wa.me/{$cleanPhone}?text={$waMessage}";
        @endphp

        <!-- WhatsApp Direct Chat Button -->
        <a href="{{ $waUrl }}" target="_blank" rel="noopener noreferrer"
            class="group/wa relative overflow-hidden w-full inline-flex items-center justify-center gap-2.5 bg-[#25D366] hover:bg-[#20bd5a] text-black font-headline font-black text-xs px-5 py-3 rounded-xl shadow-[0_0_20px_rgba(37,211,102,0.3)] hover:shadow-[0_0_25px_rgba(37,211,102,0.5)] active:scale-[0.98] transition-all uppercase tracking-wider">
            <span class="absolute inset-0 w-1/2 h-full bg-white/30 transform -skew-x-12 -translate-x-full group-hover/wa:translate-x-[300%] transition-transform duration-700 ease-out"></span>
            <svg class="w-4 h-4 fill-current flex-shrink-0" viewBox="0 0 24 24">
                <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
            </svg>
            <span>Priority WhatsApp Support</span>
        </a>

        <!-- Quick Contacts Grid (Call & Email) -->
        <div class="grid grid-cols-2 gap-2.5 pt-1">
            <a href="tel:{{ config('app.contact_phone') }}"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 bg-surface-container-low border border-white/10 hover:border-cyan-400/40 text-gray-300 hover:text-white rounded-xl transition-all text-xs font-semibold">
                <span class="material-symbols-outlined text-sm text-cyan-400">call</span>
                <span>Call Us</span>
            </a>
            <a href="mailto:{{ config('app.contact_email') }}"
                class="inline-flex items-center justify-center gap-1.5 px-3 py-2.5 bg-surface-container-low border border-white/10 hover:border-emerald-400/40 text-gray-300 hover:text-white rounded-xl transition-all text-xs font-semibold">
                <span class="material-symbols-outlined text-sm text-emerald-400">mail</span>
                <span>Email Us</span>
            </a>
        </div>
    </div>

    <!-- Support Timings Footer Note -->
    <div class="pt-3 border-t border-white/5 flex items-center justify-between text-[11px] text-gray-400 font-mono">
        <span class="flex items-center gap-1">
            <span class="material-symbols-outlined text-xs text-emerald-400">schedule</span>
            <span>Mon - Sat: 9am - 9pm</span>
        </span>
        <span class="text-emerald-400 font-bold">Avg. Reply: &lt; 5m</span>
    </div>
</div>
