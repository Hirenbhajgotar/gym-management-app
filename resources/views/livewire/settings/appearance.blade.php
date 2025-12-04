<?php

use Livewire\Volt\Component;

new class extends Component {
    //
}; ?>

<x-settings.layout :heading="__('Appearance')" :subheading="__('Update the appearance settings for your account')">
        <!-- Appearance Settings -->
        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Theme Preferences</h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                        Choose your preferred theme
                    </label>
                    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="w-full">
                        <flux:radio value="light" icon="sun" class="flex-1">{{ __('Light') }}</flux:radio>
                        <flux:radio value="dark" icon="moon" class="flex-1">{{ __('Dark') }}</flux:radio>
                        <flux:radio value="system" icon="computer-desktop" class="flex-1">{{ __('System') }}</flux:radio>
                    </flux:radio.group>
                </div>

                <div class="mt-4 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h4 class="text-sm font-medium text-blue-800 dark:text-blue-200">Theme Information</h4>
                            <div class="mt-1 text-sm text-blue-700 dark:text-blue-300">
                                <p>• <strong>Light:</strong> Always use light theme</p>
                                <p>• <strong>Dark:</strong> Always use dark theme</p>
                                <p>• <strong>System:</strong> Follow your device's theme preference</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-settings.layout>
