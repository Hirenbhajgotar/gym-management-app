<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component {
    public string $name = '';
    public string $email = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],

            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <!-- Profile Information Form -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Profile Information</h3>

            <form wire:submit="updateProfileInformation" class="space-y-6">
                <div class="grid grid-cols-1 gap-6">
                    <flux:input
                        wire:model="name"
                        :label="__('Name')"
                        type="text"
                        required
                        autofocus
                        autocomplete="name"
                        class="w-full"
                    />

                    <div class="space-y-2">
                        <flux:input
                            wire:model="email"
                            :label="__('Email')"
                            type="email"
                            required
                            autocomplete="email"
                            class="w-full"
                        />

                        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !auth()->user()->hasVerifiedEmail())
                            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <flux:text class="text-sm text-yellow-800 dark:text-yellow-200">
                                            {{ __('Your email address is unverified.') }}
                                        </flux:text>
                                        <div class="mt-2">
                                            <flux:link
                                                class="text-sm font-medium text-yellow-800 dark:text-yellow-200 hover:text-yellow-600 dark:hover:text-yellow-100 cursor-pointer underline"
                                                wire:click.prevent="resendVerificationNotification">
                                                {{ __('Click here to re-send the verification email.') }}
                                            </flux:link>
                                        </div>
                                        @if (session('status') === 'verification-link-sent')
                                            <flux:text class="mt-2 text-sm font-medium text-green-800 dark:text-green-200">
                                                {{ __('A new verification link has been sent to your email address.') }}
                                            </flux:text>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                    <x-action-message class="text-sm" on="profile-updated">
                        <div class="flex items-center text-green-600 dark:text-green-400">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            {{ __('Saved.') }}
                        </div>
                    </x-action-message>

                    <flux:button variant="primary" type="submit" class="px-6 py-2">
                        {{ __('Save Changes') }}
                    </flux:button>
                </div>
            </form>
        </div>

        <!-- Delete Account Section -->
        <livewire:settings.delete-user-form />
    </x-settings.layout>
