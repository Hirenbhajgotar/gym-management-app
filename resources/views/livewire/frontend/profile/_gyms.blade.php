<!-- Gym Locations Card (With Live Metrics & Direct Dashboard Launcher per Gym) -->
<div id="your-gyms" class="bg-[#0d1520]/90 border border-white/10 rounded-2xl p-6 sm:p-7 shadow-[0_0_40px_rgba(0,0,0,0.6)] backdrop-blur-xl space-y-4">
    <div class="flex items-center justify-between pb-3 border-b border-white/10">
        <div class="flex items-center gap-2.5">
            <span class="material-symbols-outlined text-cyan-400 text-xl">fitness_center</span>
            <h3 class="font-headline font-bold text-lg text-white uppercase tracking-tight">Your Gyms</h3>
        </div>
        <span class="px-2.5 py-0.5 rounded-full text-xs font-mono font-bold bg-cyan-500/10 text-cyan-400 border border-cyan-500/20">
            {{ $this->totalGymsCount }} {{ \Illuminate\Support\Str::plural('Branch', $this->totalGymsCount) }}
        </span>
    </div>

    @if ($this->user->gyms->count() > 0)
        <div class="space-y-4 pt-2">
            @foreach ($this->user->gyms as $gym)
                @php
                    $gymDashboardUrl = '#';
                    $gymDashboardText = 'Dashboard';
                    $isActive = (bool) ($gym->is_active && ($gym->pivot->active ?? true));

                    // Determine role-based dashboard URL for this specific gym
                    $pivotRole = $gym->pivot->role ?? null;
                    if ($this->user->hasRole('gym_owner') || $pivotRole === 'gym_owner') {
                        $gymDashboardUrl = route('filament.admin.pages.dashboard', ['tenant' => $gym->slug]);
                        $gymDashboardText = 'Admin Dashboard';
                    } elseif ($this->user->hasAnyRole(['manager', 'receptionist']) || in_array($pivotRole, ['manager', 'receptionist'])) {
                        $gymDashboardUrl = route('filament.staff.pages.dashboard', ['tenant' => $gym->slug]);
                        $gymDashboardText = 'Staff Dashboard';
                    } elseif ($gym->slug) {
                        $gymDashboardUrl = route('filament.admin.pages.dashboard', ['tenant' => $gym->slug]);
                        $gymDashboardText = 'Dashboard';
                    }

                    $hasQrToken = filled($gym->qr_code_token);
                    $checkinUrl = $hasQrToken ? $gym->getCheckInQrCodeUrl() : null;
                    $qrSvg = $hasQrToken ? $gym->getCheckInQrCodeSvg() : null;
                    $cityName = $gym->address->city->name ?? 'Location not set';
                @endphp

                <div wire:key="gym-{{ $gym->id }}" class="p-4 rounded-xl bg-surface-container-low/70 border border-white/10 hover:border-emerald-500/30 transition-all space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-0.5">
                            <h4 class="font-headline font-bold text-white text-base leading-tight">{{ $gym->name }}</h4>
                            <div class="flex items-center gap-1.5 text-xs text-gray-400">
                                <span class="material-symbols-outlined text-sm text-cyan-400">location_on</span>
                                <span>{{ $cityName }}</span>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold font-mono {{ $isActive ? 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30' : 'bg-gray-800 text-gray-400 border border-gray-700' }}">
                            <span class="w-1.5 h-1.5 rounded-full {{ $isActive ? 'bg-emerald-400 animate-pulse' : 'bg-gray-500' }}"></span>
                            {{ $isActive ? 'ACTIVE' : 'INACTIVE' }}
                        </span>
                    </div>

                    <!-- Quick Branch Counts Bar -->
                    <div class="grid grid-cols-3 gap-2 pt-1 pb-1 text-center">
                        <div class="bg-black/30 border border-white/5 rounded-lg py-1.5 px-2">
                            <div class="text-[10px] font-bold font-mono uppercase text-gray-400">Members</div>
                            <div class="text-sm font-headline font-bold text-cyan-300">{{ $gym->members_count ?? 0 }}</div>
                        </div>
                        <div class="bg-black/30 border border-white/5 rounded-lg py-1.5 px-2">
                            <div class="text-[10px] font-bold font-mono uppercase text-gray-400">Staff</div>
                            <div class="text-sm font-headline font-bold text-white">{{ $gym->users_count ?? 0 }}</div>
                        </div>
                        <div class="bg-black/30 border border-white/5 rounded-lg py-1.5 px-2">
                            <div class="text-[10px] font-bold font-mono uppercase text-gray-400">Gates</div>
                            <div class="text-sm font-headline font-bold text-emerald-400">{{ $gym->biometric_devices_count ?? 0 }}</div>
                        </div>
                    </div>

                    <!-- Actions: QR Code / Copy / Launch Dashboard -->
                    <div class="pt-2 border-t border-white/5 space-y-2">
                        @if ($hasQrToken && $checkinUrl)
                            <div class="grid grid-cols-2 gap-2">
                                <!-- Copy Link -->
                                <button type="button"
                                    @click="copyToClipboard(@js($checkinUrl), {{ $gym->id }})"
                                    class="inline-flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-lg bg-surface-container-high/80 hover:bg-surface-container-highest border border-white/10 text-[11px] font-semibold text-gray-300 hover:text-cyan-300 transition-all cursor-pointer">
                                    <span class="material-symbols-outlined text-sm" :class="copiedGymId === {{ $gym->id }} ? 'text-emerald-400' : 'text-cyan-400'" x-text="copiedGymId === {{ $gym->id }} ? 'check_circle' : 'link'"></span>
                                    <span x-text="copiedGymId === {{ $gym->id }} ? 'Copied!' : 'Copy Link'"></span>
                                </button>

                                <!-- Preview QR Code -->
                                <button type="button"
                                    @click="activeQrGym = { id: {{ $gym->id }}, name: @js($gym->name), checkinUrl: @js($checkinUrl), city: @js($cityName), qrSvg: @js($qrSvg) }; showQrModal = true"
                                    class="inline-flex items-center justify-center gap-1.5 py-2 px-2.5 rounded-lg bg-surface-container-high/80 hover:bg-surface-container-highest border border-white/10 text-[11px] font-semibold text-gray-300 hover:text-emerald-300 transition-all cursor-pointer">
                                    <span class="material-symbols-outlined text-sm text-emerald-400">qr_code_2</span>
                                    <span>View QR</span>
                                </button>
                            </div>
                        @endif

                        @if ($gymDashboardUrl !== '#' && $this->user->status === \App\Enums\UserStatus::ACTIVE)
                            <a href="{{ $gymDashboardUrl }}"
                                class="group/btn relative overflow-hidden w-full inline-flex items-center justify-center gap-2 bg-[#22c55e] hover:bg-[#16a34a] text-black font-headline font-black text-xs px-4 py-2.5 rounded-lg shadow-[0_0_15px_rgba(34,197,94,0.3)] hover:shadow-[0_0_20px_rgba(34,197,94,0.5)] active:scale-[0.98] transition-all uppercase tracking-wider">
                                <span class="absolute inset-0 w-1/2 h-full bg-white/30 transform -skew-x-12 -translate-x-full group-hover/btn:translate-x-[300%] transition-transform duration-700 ease-out"></span>
                                <span class="material-symbols-outlined text-base font-bold">dashboard</span>
                                <span>Launch {{ $gymDashboardText }}</span>
                                <span class="material-symbols-outlined text-sm group-hover/btn:translate-x-1 transition-transform">arrow_forward</span>
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="py-8 text-center space-y-2">
            <span class="material-symbols-outlined text-gray-600 text-4xl">store</span>
            <p class="text-xs text-gray-400 font-medium">No gym branches registered under this account.</p>
        </div>
    @endif
</div>
