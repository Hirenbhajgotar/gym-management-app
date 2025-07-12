{{-- resources/views/livewire/gym-owner-register.blade.php --}}
<div class="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        {{-- Header --}}
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold text-gray-900 mb-2">Business Registration</h1>
            <p class="text-lg text-gray-600">Join our network of fitness professionals</p>
        </div>

        {{-- Progress Bar --}}
        <div class="mb-8">
            <div class="flex items-center justify-between mb-4">
                @for ($i = 1; $i <= $totalSteps; $i++)
                    <div class="flex items-center {{ $i < $totalSteps ? 'flex-1' : '' }}">
                        <div
                            class="flex items-center justify-center w-10 h-10 rounded-full border-2 transition-all duration-300 {{ $i <= $currentStep ? 'bg-blue-600 border-blue-600 text-white' : 'border-gray-300 text-gray-500' }}">
                            @if ($i < $currentStep)
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                        clip-rule="evenodd"></path>
                                </svg>
                            @else
                                <span class="text-sm font-medium">{{ $i }}</span>
                            @endif
                        </div>
                        @if ($i < $totalSteps)
                            <div class="flex-1 h-0.5 mx-4 {{ $i < $currentStep ? 'bg-blue-600' : 'bg-gray-300' }}">
                            </div>
                        @endif
                    </div>
                @endfor
            </div>
            <div class="text-center">
                <p class="text-sm text-gray-600">Step {{ $currentStep }} of {{ $totalSteps }}:
                    {{ $this->getStepTitle($currentStep) }}</p>
            </div>
        </div>

        {{-- Flash Messages --}}
        @if (session()->has('success'))
            <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative"
                role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @elseif (session()->has('error'))
            <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        {{-- Form Container --}}
        <div class="bg-white rounded-xl shadow-lg p-8">
            {{-- Step Header --}}
            <div class="mb-8 text-center">
                <div class="flex justify-center mb-4">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="{{ $this->getStepIcon($currentStep) }}"></path>
                        </svg>
                    </div>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 mb-2">{{ $this->getStepTitle($currentStep) }}</h2>
                <p class="text-gray-600">{{ $this->getStepDescription($currentStep) }}</p>
            </div>

            <form wire:submit="submit">
                {{-- Step 1: Personal Information --}}
                @if ($currentStep === 1)
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">Full Name
                                    *</label>
                                <div class="relative">
                                    <input type="text" wire:model="name" id="name"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="John Doe">
                                </div>
                                @error('name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 md:grid-cols-2 gap-6">
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address
                                    *</label>
                                <div class="relative">
                                    <input type="email" wire:model="email" id="email"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="john@example.com">
                                </div>
                                @error('email')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number
                                    *</label>
                                <div class="relative">
                                    <input type="tel" wire:model="phone" id="phone"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="+1 (555) 123-4567">
                                </div>
                                @error('phone')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div x-data="{ 
                                showPassword: false, 
                                showHelp: false,
                                generating: false,
                                async generatePassword() {
                                    this.generating = true;
                                    try {
                                        await $wire.generatePassword();
                                        this.showPassword = true;
                                        setTimeout(() => this.showPassword = false, 3000);
                                    } catch (error) {
                                        console.error('Error generating password:', error);
                                    } finally {
                                        this.generating = false;
                                    }
                                }
                            }">
                                <label for="password" class="text-sm font-medium text-gray-700 mb-2">
                                    Password *
                                    <button type="button" @click="showHelp = !showHelp"  aria-label="Toggle password requirements" class="ml-1 text-gray-400 hover:text-gray-600">
                                        <svg class="h-4 w-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </button>
                                </label>
                                <a href="javascript:void(0)" @click.prevent="generatePassword()" 
                                class="text-sm float-right !text-green-600 hover:!text-green-700 font-medium transition-colors duration-200 flex items-center gap-1"
                                :class="{ 'opacity-50 cursor-not-allowed': generating }">
                                    <svg x-show="!generating" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                    </svg>
                                    <svg x-show="generating" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span x-text="generating ? 'Generating...' : 'Generate Password'"></span>
                                </a>
                                <div class="relative">
                                    <input :type="showPassword ? 'text' : 'password'" wire:model="password"
                                        id="password"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md  leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Enter password">
                                    <button type="button" @click="showPassword = !showPassword"
                                        class="absolute inset-y-0 right-0 pr-3 !pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                                        <svg x-show="!showPassword" class="h-5 w-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                            </path>
                                        </svg>
                                        <svg x-show="showPassword" class="h-5 w-5" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21">
                                            </path>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Password Requirements Helper Text -->
                                <div x-show="showHelp" x-transition class="mt-2 p-3 bg-blue-50 border border-blue-200 rounded-md">
                                    <h4 class="text-sm font-medium text-blue-900 mb-2">Password Requirements:</h4>
                                    <ul class="text-sm text-blue-800 space-y-1">
                                        <li class="flex items-center">
                                            <svg class="h-4 w-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            At least 8 characters long
                                        </li>
                                        <li class="flex items-center">
                                            <svg class="h-4 w-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Include uppercase letters (A-Z)
                                        </li>
                                        <li class="flex items-center">
                                            <svg class="h-4 w-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Include lowercase letters (a-z)
                                        </li>
                                        <li class="flex items-center">
                                            <svg class="h-4 w-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Include numbers (0-9)
                                        </li>
                                        <li class="flex items-center">
                                            <svg class="h-4 w-4 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Include special characters (!@#$%^&*)
                                        </li>
                                    </ul>
                                    <p class="text-xs text-blue-700 mt-2">
                                        <strong>Tip:</strong> Use a passphrase with mixed case, numbers, and symbols for better security.
                                    </p>
                                </div>

                                <!-- Simple Helper Text (Alternative - always visible) -->
                                <p class="mt-1 text-sm text-gray-600">
                                    Choose a strong password with at least 8 characters, including uppercase, lowercase, numbers, and symbols.
                                    <span class="text-green-600 font-medium">Or click "Generate" for a secure password.</span>
                                </p>

                                @error('password')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div x-data="{ showPassword: false }">
                                <label for="password_confirmation"
                                    class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                                <div class="relative">
                                    <input :type="showPassword ? 'text' : 'password'"
                                        wire:model="password_confirmation" id="password_confirmation"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md  leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="Confirm password">
                                    <button type="button" @click="showPassword = !showPassword"
                                        class="absolute inset-y-0 right-0 pr-3 !pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none">
                                        <svg x-show="!showPassword" class="h-5 w-5" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                            </path>
                                        </svg>
                                        <svg x-show="showPassword" class="h-5 w-5" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21">
                                            </path>
                                        </svg>
                                    </button>
                                </div>
                                @error('password_confirmation')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Step 2: Business Information --}}
                @if ($currentStep === 2)
                    <div class="space-y-6">
                        <div>
                            <label for="gym_name" class="block text-sm font-medium text-gray-700 mb-2">Business Name
                                *</label>
                            <div class="relative">
                                <input type="text" wire:model="gym_name" id="gym_name"
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="FitLife Gym">
                            </div>
                            @error('gym_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-3 md:grid-cols-3 gap-6">
                            <div>
                                <label for="business_type"
                                    class="block text-sm font-medium text-gray-700 mb-2">Business
                                    Type *</label>
                                <div class="relative">
                                    <select wire:model="business_type" id="business_type"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Business Type</option>
                                        @foreach ($this->getBusinessTypes() as $businessType)
                                            <option value="{{ $businessType->id }}">{{ $businessType->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('business_type')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="number_of_locations" class="block text-sm font-medium text-gray-700 mb-2">
                                    Number of locations *</label>
                                <div class="relative">
                                    <select wire:model="number_of_locations" id="number_of_locations"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Location Type</option>
                                        <option value="single">New (about to open our first location)</option>
                                        <option value="multiple">We have multiple locations</option>
                                    </select>
                                </div>
                                @error('number_of_locations')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="years_in_business"
                                    class="block text-sm font-medium text-gray-700 mb-2">Years in Business</label>
                                <div class="relative">
                                    <input type="number" wire:model="years_in_business" id="years_in_business"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="5" min="0" max="50">
                                </div>
                                @error('years_in_business')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="business_description"
                                class="block text-sm font-medium text-gray-700 mb-2">Business Description</label>
                            <div class="relative">
                                <textarea wire:model="business_description" id="business_description" rows="4"
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Describe your gym, services, and what makes it unique..."></textarea>
                            </div>
                            @error('business_description')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif

                {{-- Step 3: Location Details --}}
                @if ($currentStep === 3)
                    <div class="space-y-6">
                        <div>
                            <label for="street" class="block text-sm font-medium text-gray-700 mb-2">
                                Street Address *
                            </label>
                            <div class="relative">
                                <input type="text" wire:model="address.street" id="street"
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="123 Main Street">
                            </div>
                            @error('address.street')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-3 md:grid-cols-3 gap-6">
                            <div>
                                <label for="country_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Country *
                                </label>
                                <div class="relative">
                                    <select wire:model="address.country_id" id="country_id"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        wire:change="$refresh">
                                        <option value="">Select Country</option>
                                        @foreach (\App\Models\Country::where('name', 'like', '%')->pluck('name', 'id') as $id => $name)
                                            <option value="{{ $id }}">{{ $name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('address.country_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="state_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    State / Province *
                                </label>
                                <div class="relative">
                                    <select wire:model="address.state_id" id="state_id"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        wire:change="$refresh" {{ $address['country_id'] == '' ? 'disabled' : '' }}>
                                        <option value="">Select State</option>
                                        @if ($address['country_id'])
                                            @foreach (\App\Models\State::where('country_id', $address['country_id'])->active()->pluck('name', 'id') as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                @error('address.state_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="city_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    City *
                                </label>
                                <div class="relative">
                                    <select wire:model="address.city_id" id="city_id"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md leading-5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        wire:change="$refresh" {{ $address['state_id'] == '' ? 'disabled' : '' }}>
                                        <option value="">Select City</option>
                                        @if ($address['state_id'])
                                            @foreach (\App\Models\City::where('state_id', $address['state_id'])->active()->pluck('name', 'id') as $id => $name)
                                                <option value="{{ $id }}">{{ $name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>
                                @error('address.city_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-3 md:grid-cols-3 gap-6">
                            <div>
                                <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-2">
                                    Zip / Postal Code *
                                </label>
                                <div class="relative">
                                    <input type="text" wire:model="address.postal_code" id="postal_code"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="e.g., 90210">
                                </div>
                                @error('address.postal_code')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="latitude"
                                    class="block text-sm font-medium text-gray-700 mb-2">Latitude</label>
                                <div class="relative">
                                    <input type="number" wire:model="address.latitude" id="latitude"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        step="0.000001" min="-90" max="90">
                                </div>
                                @error('address.latitude')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="longitude"
                                    class="block text-sm font-medium text-gray-700 mb-2">Longitude</label>
                                <div class="relative">
                                    <input type="number" wire:model="address.longitude" id="longitude"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        step="0.000001" min="-180" max="180">
                                </div>
                                @error('address.longitude')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Step 4: Additional Information --}}
                @if ($currentStep === 4)
                    <div class="space-y-6">
                        <div class="grid grid-cols-2 md:grid-cols-2 gap-6">
                            <div>
                                <label for="website" class="block text-sm font-medium text-gray-700 mb-2">Website
                                    URL</label>
                                <div class="relative">
                                    <input type="url" wire:model="website" id="website"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                        placeholder="https://www.yourwebsite.com">
                                </div>
                                @error('website')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="gym_size" class="block text-sm font-medium text-gray-700 mb-2">
                                    Business Size
                                </label>
                                <div class="relative">
                                    <select wire:model="gym_size" id="gym_size"
                                        class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                                        <option value="">Select Business Size</option>
                                        @foreach ($this->getGymSizes() as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                @error('gym_size')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="special_requirements"
                                class="block text-sm font-medium text-gray-700 mb-2">Special Requirements</label>
                            <div class="relative">
                                <textarea wire:model="special_requirements" id="special_requirements" rows="4"
                                    class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md !rounded-md leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="Any special requirements or additional information..."></textarea>
                            </div>
                            @error('special_requirements')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                @endif

                {{-- Step 5: Thank You --}}
                @if ($currentStep === 5)
                    <div class="space-y-6 text-center">
                        <div class="flex justify-center mb-4">
                            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-green-600" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-2">Thank You for Registering!</h2>
                        <p class="text-gray-600 mb-4">Your registration has been successfully submitted. We will review your application and contact you soon at <strong>{{ $email }}</strong>. In the meantime, you can explore our website.</p>
                        <div class="mt-6">
                            <x-filament::button href="{{ route('home') }}" tag="a" color="gray"
                                class="text-gray-100 !text-gray-100 bg-blue-600 !bg-blue-600 hover:bg-blue-700 !hover:bg-blue-700">
                                Go to Homepage
                            </x-filament::button>
                        </div>
                    </div>
                @endif

                {{-- Navigation Buttons --}}
                <div class="flex justify-between items-center mt-8 pt-6 border-t border-gray-200">
                    <div>
                        @if ($currentStep > 1 && $currentStep < $totalSteps)
                            <x-filament::button type="button" icon="heroicon-o-arrow-long-left"
                                wire:loading.attr="disabled" wire:click="previousStep" wire:target="previousStep"
                                color="gray">
                                Back
                            </x-filament::button>
                        @endif
                    </div>
                    <div>
                        @if ($currentStep < ($totalSteps - 1))
                            <x-filament::button type="button" icon="heroicon-o-arrow-long-right"
                                icon-position="after" wire:loading.attr="disabled" wire:click="nextStep"
                                wire:target="nextStep" color="gray">
                                Next
                            </x-filament::button>
                        @elseif ($currentStep === ($totalSteps - 1))
                            <x-filament::button type="submit" wire:loading.attr="disabled"
                                wire:target="submit" color="gray"
                                class="text-gray-100 !text-gray-100 bg-blue-600 !bg-blue-600 hover:bg-blue-700 !hover:bg-blue-700">
                                Submit
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
