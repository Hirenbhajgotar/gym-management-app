<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    @php($isPrivate = $isPrivate())
    @php($mode = $getMode())
    @php($disabled = $isDisabled())
    <div 
        x-data="{
            length: {{ $getLength() }},
            statePath: '{{ $getStatePath() }}',
            otpId: null,
            container: null,
            init() {
                this.container = this.$el;
                this.otpId = this.$el.closest('[wire\\:id]')?.getAttribute('wire:id');
                const current = this.$wire.get(this.statePath);
                if (current && typeof current === 'string') {
                    const inputs = this.container.querySelectorAll('input');
                    current.split('').forEach((char, i) => {
                        if (inputs[i]) inputs[i].value = char;
                    });
                }
            },
            getInputs() {
                return this.container ? this.container.querySelectorAll('input') : [];
            },
            handleInput(index, event) {
                const inputs = this.getInputs();
                const value = event.target.value;
                
                if (value && index < this.length - 1) {
                    setTimeout(() => {
                        const nextInputs = this.getInputs();
                        if (nextInputs[index + 1]) {
                            nextInputs[index + 1].focus();
                        }
                    }, 0);
                }
                
                this.syncState();
            },
            handleFocus(event) {
                // Select the entire value when field receives focus via keyboard
                // but not when clicked with mouse
                event.target.select();
            },
            handleKeydown(index, event) {
                const inputs = this.getInputs();
                
                // Select current field value on backspace if field has content
                {{-- if (event.key === 'Backspace' && event.target.value) {
                    event.target.select();
                    return;
                } --}}
                
                if (event.key === 'Backspace' && index > 0) {
                    setTimeout(() => {
                        const prevInputs = this.getInputs();
                        if (prevInputs[index - 1]) {
                            prevInputs[index - 1].focus();
                            prevInputs[index - 1].select();
                        }
                    }, 0);
                }
            },
            handlePaste(event) {
                event.preventDefault();
                const inputs = this.getInputs();
                const paste = event.clipboardData?.getData('text')?.substring(0, this.length) || '';
                
                paste.split('').forEach((char, i) => {
                    if (inputs[i]) inputs[i].value = char;
                });
                
                if (paste.length > 0) {
                    const focusIndex = Math.min(paste.length, this.length - 1);
                    setTimeout(() => {
                        const newInputs = this.getInputs();
                        if (newInputs[focusIndex]) {
                            newInputs[focusIndex].focus();
                        }
                    }, 0);
                }
                
                this.syncState();
            },
            syncState() {
                const inputs = this.getInputs();
                const values = Array.from(inputs).map(i => i.value).join('');
                if (this.otpId && values) {
                    window.Livewire.find(this.otpId).set(this.statePath, values);
                }
            }
        }"
        x-init="init()"
        class="flex gap-2 w-full"
    >
        <template x-for="(item, index) in Array(length).fill(0)" :key="index">
            <input
                type="{{ $isPrivate ? 'password' : 'text' }}"
                maxlength="1"
                inputmode="{{ $mode === 'numeric' ? 'numeric' : ($mode === 'alpha' ? 'text' : 'alphanumeric') }}"
                class="flex-1 w-full h-10 px-3 py-2 text-center text-sm font-medium border border-gray-300 rounded-lg shadow-sm transition-colors focus:border-primary-500 focus:ring-1-test focus:ring-primary-500-test focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed dark:bg-gray-800 dark:border-gray-600 dark:text-white dark:placeholder-gray-400"
                x-on:focus="handleFocus($event)"
                x-on:input="handleInput(index, $event)"
                x-on:keydown="handleKeydown(index, $event)"
                x-on:paste="handlePaste($event)"
                x-on:keydown="handleKeydown(index, $event)"
                x-on:paste="handlePaste($event)"
                {{ $disabled ? 'disabled' : '' }}
            />
        </template>
    </div>
</x-dynamic-component>
