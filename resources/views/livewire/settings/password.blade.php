<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component {
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<x-settings.layout :heading="__('Update Password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <!-- Password Update Form -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Change Password</h3>

            <form wire:submit="updatePassword" class="space-y-6">
                <div class="grid grid-cols-1 gap-6">
                    <flux:input
                        wire:model="current_password"
                        :label="__('Current Password')"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full"
                    />

                    <flux:input
                        wire:model="password"
                        :label="__('New Password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full"
                    />

                    <flux:input
                        wire:model="password_confirmation"
                        :label="__('Confirm New Password')"
                        type="password"
                        required
                        autocomplete="new-password"
                        class="w-full"
                    />
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                    <x-action-message class="text-sm" on="password-updated">
                        <div class="flex items-center text-green-600 dark:text-green-400">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            {{ __('Password updated successfully.') }}
                        </div>
                    </x-action-message>

                    <flux:button variant="primary" type="submit" class="px-6 py-2">
                        {{ __('Update Password') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </x-settings.layout>
