<!-- 1. Account & Security Settings Modal -->
<div x-show="showSettingsModal" 
    x-cloak
    role="dialog"
    aria-modal="true"
    aria-labelledby="settings-modal-title"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0">
    
    <!-- Dark Blur Backdrop -->
    <div class="fixed inset-0 bg-black/80 backdrop-blur-md transition-opacity"
        @click="showSettingsModal = false"></div>

    <!-- Modal Dialog Container -->
    <div class="relative w-full max-w-xl bg-[#0d1520] border border-white/15 rounded-3xl shadow-[0_0_60px_rgba(0,0,0,0.9)] overflow-hidden z-10 my-8"
        x-show="showSettingsModal"
        x-transition:enter="transition ease-out duration-300 transform"
        x-transition:enter-start="opacity-0 scale-95 translate-y-4"
        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-200 transform"
        x-transition:leave-start="opacity-100 scale-100 translate-y-0"
        x-transition:leave-end="opacity-0 scale-95 translate-y-4"
        @click.stop>
        
        <!-- Ambient Accent Glow -->
        <div class="absolute -top-16 -right-16 w-48 h-48 bg-emerald-500/15 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute -bottom-16 -left-16 w-48 h-48 bg-cyan-500/15 rounded-full blur-3xl pointer-events-none"></div>

        <!-- Modal Header with Tabs -->
        <div class="p-6 sm:p-7 border-b border-white/10 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400">
                        <span class="material-symbols-outlined text-xl">manage_accounts</span>
                    </div>
                    <div>
                        <h3 id="settings-modal-title" class="font-headline font-bold text-xl text-white">Account Settings</h3>
                        <p class="text-xs text-gray-400">Manage your profile details and security credentials</p>
                    </div>
                </div>

                <!-- Close Button -->
                <button @click="showSettingsModal = false" type="button"
                    class="p-2 rounded-xl text-gray-400 hover:text-white bg-surface-container-low hover:bg-surface-container-high border border-white/10 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-lg">close</span>
                </button>
            </div>

            <!-- Navigation Tabs -->
            <div class="flex rounded-xl bg-[#080d16] p-1 border border-white/10">
                <button @click="settingsTab = 'profile'" type="button"
                    class="flex-1 inline-flex items-center justify-center gap-2 py-2 px-4 rounded-lg text-xs font-headline font-bold uppercase tracking-wider transition-all cursor-pointer"
                    :class="settingsTab === 'profile' ? 'bg-[#22c55e] text-black shadow-lg shadow-emerald-500/20' : 'text-gray-400 hover:text-white'">
                    <span class="material-symbols-outlined text-base">person</span>
                    <span>Profile Info</span>
                </button>
                <button @click="settingsTab = 'security'" type="button"
                    class="flex-1 inline-flex items-center justify-center gap-2 py-2 px-4 rounded-lg text-xs font-headline font-bold uppercase tracking-wider transition-all cursor-pointer"
                    :class="settingsTab === 'security' ? 'bg-[#22c55e] text-black shadow-lg shadow-emerald-500/20' : 'text-gray-400 hover:text-white'">
                    <span class="material-symbols-outlined text-base">lock</span>
                    <span>Security & Password</span>
                </button>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="p-6 sm:p-7">
            <!-- Tab 1: Profile Information -->
            <div x-show="settingsTab === 'profile'">
                <form wire:submit.prevent="updateProfile" class="space-y-4">
                    <!-- Full Name -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-300">
                            Full Name <span class="text-emerald-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined text-gray-400 text-lg absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">person</span>
                            <input type="text" wire:model="name"
                                class="w-full pl-10 pr-4 py-3 bg-surface-container-low border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-all placeholder-gray-500"
                                placeholder="Enter your full name" required>
                        </div>
                        @error('name')
                            <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Phone Number -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-300">
                            Phone / WhatsApp Number
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined text-gray-400 text-lg absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">call</span>
                            <input type="tel" wire:model="phone"
                                class="w-full pl-10 pr-4 py-3 bg-surface-container-low border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-all placeholder-gray-500"
                                placeholder="+91 98765 43210">
                        </div>
                        @error('phone')
                            <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-white/10">
                        <button @click="showSettingsModal = false" type="button"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-white bg-surface-container-low hover:bg-surface-container-high transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="group relative overflow-hidden inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-[#22c55e] hover:bg-[#16a34a] text-black font-headline font-black text-xs rounded-xl shadow-[0_0_15px_rgba(34,197,94,0.3)] hover:shadow-[0_0_20px_rgba(34,197,94,0.5)] active:scale-[0.98] transition-all uppercase tracking-wider cursor-pointer disabled:opacity-50">
                            <span class="absolute inset-0 w-1/2 h-full bg-white/30 transform -skew-x-12 -translate-x-full group-hover:translate-x-[300%] transition-transform duration-700 ease-out"></span>
                            <span wire:loading.remove wire:target="updateProfile" class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base">save</span>
                                <span>Save Profile</span>
                            </span>
                            <span wire:loading.flex wire:target="updateProfile" class="items-center gap-2">
                                <svg class="w-4 h-4 animate-spin text-black" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Saving...</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Tab 2: Security & Password -->
            <div x-show="settingsTab === 'security'">
                <form wire:submit.prevent="updatePassword" class="space-y-4">
                    <!-- Current Password -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-300">
                            Current Password <span class="text-emerald-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined text-gray-400 text-lg absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">key</span>
                            <input type="password" wire:model="current_password"
                                class="w-full pl-10 pr-4 py-3 bg-surface-container-low border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-all placeholder-gray-500"
                                placeholder="••••••••••••" required>
                        </div>
                        @error('current_password')
                            <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- New Password -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-300">
                            New Password <span class="text-emerald-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined text-gray-400 text-lg absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">lock</span>
                            <input type="password" wire:model="password"
                                class="w-full pl-10 pr-4 py-3 bg-surface-container-low border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-all placeholder-gray-500"
                                placeholder="Min. 8 characters" required>
                        </div>
                        @error('password')
                            <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div class="space-y-1.5">
                        <label class="block text-xs font-bold uppercase tracking-widest text-gray-300">
                            Confirm New Password <span class="text-emerald-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="material-symbols-outlined text-gray-400 text-lg absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none">lock_reset</span>
                            <input type="password" wire:model="password_confirmation"
                                class="w-full pl-10 pr-4 py-3 bg-surface-container-low border border-white/10 rounded-xl text-white text-sm focus:outline-none focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 transition-all placeholder-gray-500"
                                placeholder="Repeat new password" required>
                        </div>
                        @error('password_confirmation')
                            <p class="text-xs text-rose-400 mt-1 font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Password Requirement Tip -->
                    <div class="p-3 bg-surface-container-low rounded-xl border border-white/5 space-y-1 text-[11px] text-gray-400">
                        <div class="font-bold text-gray-300 flex items-center gap-1">
                            <span class="material-symbols-outlined text-xs text-emerald-400">shield</span>
                            <span>Password Recommendations:</span>
                        </div>
                        <p>Use at least 8 characters including letters, numbers, and special symbols for maximum account security.</p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="pt-4 flex items-center justify-end gap-3 border-t border-white/10">
                        <button @click="showSettingsModal = false" type="button"
                            class="px-5 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider text-gray-400 hover:text-white bg-surface-container-low hover:bg-surface-container-high transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit" wire:loading.attr="disabled"
                            class="group relative overflow-hidden inline-flex items-center justify-center gap-2 px-6 py-2.5 bg-[#22c55e] hover:bg-[#16a34a] text-black font-headline font-black text-xs rounded-xl shadow-[0_0_15px_rgba(34,197,94,0.3)] hover:shadow-[0_0_20px_rgba(34,197,94,0.5)] active:scale-[0.98] transition-all uppercase tracking-wider cursor-pointer disabled:opacity-50">
                            <span class="absolute inset-0 w-1/2 h-full bg-white/30 transform -skew-x-12 -translate-x-full group-hover:translate-x-[300%] transition-transform duration-700 ease-out"></span>
                            <span wire:loading.remove wire:target="updatePassword" class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base">security</span>
                                <span>Update Password</span>
                            </span>
                            <span wire:loading.flex wire:target="updatePassword" class="items-center gap-2">
                                <svg class="w-4 h-4 animate-spin text-black" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>Updating...</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
