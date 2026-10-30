@once
    <style>[x-cloak] { display: none !important; }</style>
@endonce

<div class="space-y-3">
    @if (filled($secret))
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ $description }}
        </p>

        <div
            class="flex items-center gap-2"
            x-data="{
                copied: false,
                failed: false,
                _copiedTimer: null,
                copy(value, input) {
                    const inputEl = input || this.$refs?.secret || document.getElementById('one-time-secret-value');
                    const text = String(value ?? (inputEl ? inputEl.value : ''));

                    const markCopied = () => {
                        this.copied = true;
                        this.failed = false;
                        clearTimeout(this._copiedTimer);
                        this._copiedTimer = setTimeout(() => { this.copied = false; }, 2000);
                        if (typeof FilamentNotification !== 'undefined') {
                            new FilamentNotification()
                                .title('Copied to clipboard')
                                .success()
                                .send();
                        }
                    };

                    const markFailed = () => {
                        this.copied = false;
                        this.failed = true;
                        if (inputEl) {
                            inputEl.focus();
                            inputEl.select();
                            if (typeof inputEl.setSelectionRange === 'function') {
                                inputEl.setSelectionRange(0, text.length || 99999);
                            }
                        }
                    };

                    const fallback = () => {
                        try {
                            if (inputEl) {
                                const wasReadOnly = inputEl.hasAttribute('readonly');
                                inputEl.removeAttribute('readonly');
                                inputEl.focus();
                                inputEl.select();
                                if (typeof inputEl.setSelectionRange === 'function') {
                                    inputEl.setSelectionRange(0, text.length || 99999);
                                }
                                const ok = document.execCommand('copy');
                                if (wasReadOnly) {
                                    inputEl.setAttribute('readonly', '');
                                }
                                if (ok) {
                                    markCopied();
                                } else {
                                    markFailed();
                                }
                            } else {
                                markFailed();
                            }
                        } catch (e) {
                            markFailed();
                        }
                    };

                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(text)
                            .then(markCopied)
                            .catch(() => {
                                fallback();
                            });
                    } else {
                        fallback();
                    }
                },
            }"
        >
            <label for="one-time-secret-value" class="sr-only">One-time secret</label>
            <input
                id="one-time-secret-value"
                x-ref="secret"
                type="text"
                readonly
                value="{{ $secret }}"
                class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 font-mono text-sm text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                onclick="this.select()"
            >
            <x-filament::button
                color="gray"
                size="sm"
                type="button"
                x-bind:aria-label="copied ? 'Copied to clipboard' : (failed ? 'Select &amp; copy' : 'Copy one-time secret to clipboard')"
                x-on:click="copy($refs.secret ? $refs.secret.value : '', $refs.secret)"
            >
                <span x-show="! copied && ! failed">Copy</span>
                <span x-show="copied" x-cloak>Copied</span>
                <span x-show="failed" x-cloak>Select &amp; copy</span>
            </x-filament::button>
            <span
                class="sr-only"
                role="status"
                aria-live="polite"
                x-text="copied ? 'Copied to clipboard' : (failed ? 'Copy failed. The value is selected; press Control or Command plus C to copy.' : '')"
            ></span>
        </div>

        <p class="text-xs text-amber-600 dark:text-amber-400">
            This value is not stored in your session. It remains available in this dialog for a few minutes; copy it now.
        </p>
    @else
        <p class="text-sm text-amber-600 dark:text-amber-400">
            This one-time value is no longer available. {{ $unavailableAction ?? 'Rotate' }} it to generate a new one.
        </p>
    @endif
</div>
