@php
    $appUrl = $appUrl ?? config('app.url');
    $parsedUrl = parse_url($appUrl ?: 'http://localhost');
    $serverHost = $parsedUrl['host'] ?? request()->getHost();
    $scheme = $parsedUrl['scheme'] ?? (request()->isSecure() ? 'https' : 'http');
    $serverPort = $parsedUrl['port'] ?? ($scheme === 'https' ? '443' : '80');
    $fullEndpoint = rtrim($appUrl ?: request()->root(), '/') . '/api/iclock/cdata';
    $commKeyStatus = $commKeyStatus ?? 'Not configured';
@endphp

@once
    <style>[x-cloak] { display: none !important; }</style>
@endonce

<div
    class="rounded-xl border border-primary-500/20 bg-primary-50/50 p-5 dark:border-primary-500/30 dark:bg-primary-950/20 text-sm"
    x-data="{
        copied: null,
        failed: null,
        copy(value, key) {
            const markCopied = () => {
                this.copied = key;
                this.failed = null;
                setTimeout(() => { if (this.copied === key) { this.copied = null; } }, 2000);
            };
            const markFailed = () => {
                this.copied = null;
                this.failed = key;
                setTimeout(() => { if (this.failed === key) { this.failed = null; } }, 4000);
            };
            const fallback = () => {
                const scratch = document.createElement('textarea');
                scratch.value = value;
                scratch.setAttribute('readonly', '');
                scratch.style.position = 'fixed';
                scratch.style.opacity = '0';
                document.body.appendChild(scratch);
                scratch.select();
                let ok = false;
                try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
                document.body.removeChild(scratch);
                if (ok) { markCopied(); } else { markFailed(); }
            };
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(value).then(markCopied).catch(fallback);
            } else {
                fallback();
            }
        },
    }"
>
    <div class="flex items-center gap-3 pb-3 border-b border-primary-500/10 dark:border-primary-500/20">
        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-500/10 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
            <x-heroicon-o-signal class="h-6 w-6" />
        </div>
        <div>
            <h4 class="text-base font-semibold text-gray-900 dark:text-white">
                ADMS Terminal Connection Parameters
            </h4>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Configure these exact values inside your biometric hardware menu (eSSL / ZKTeco).
            </p>
        </div>
    </div>

    <!-- Parameter Grid -->
    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <!-- Server Address / Domain -->
        <div class="rounded-lg bg-white p-3 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Server Address / Domain</span>
                <button type="button"
                        aria-label="Copy server address"
                        @click="copy(@js($serverHost), 'server-address')"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 text-xs flex items-center gap-1 font-medium transition">
                    <span x-show="copied !== 'server-address' && failed !== 'server-address'">Copy</span>
                    <span x-show="copied === 'server-address'" x-cloak class="text-emerald-600 dark:text-emerald-400 font-semibold">Copied!</span>
                    <span x-show="failed === 'server-address'" x-cloak class="text-danger-600 dark:text-danger-400 font-semibold">Copy failed — select manually</span>
                </button>
            </div>
            <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-gray-100 select-all">
                {{ $serverHost }}
            </div>
            <div class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                Enter without http:// or path
            </div>
            <span
                class="sr-only"
                role="status"
                aria-live="polite"
                x-text="copied === 'server-address' ? 'Server address copied to clipboard' : (failed === 'server-address' ? 'Copy failed. Select the server address manually.' : '')"
            ></span>
        </div>

        <!-- Server Port -->
        <div class="rounded-lg bg-white p-3 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Server Port</span>
                <button type="button"
                        aria-label="Copy server port"
                        @click="copy(@js((string) $serverPort), 'server-port')"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 text-xs flex items-center gap-1 font-medium transition">
                    <span x-show="copied !== 'server-port' && failed !== 'server-port'">Copy</span>
                    <span x-show="copied === 'server-port'" x-cloak class="text-emerald-600 dark:text-emerald-400 font-semibold">Copied!</span>
                    <span x-show="failed === 'server-port'" x-cloak class="text-danger-600 dark:text-danger-400 font-semibold">Copy failed — select manually</span>
                </button>
            </div>
            <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-gray-100 select-all">
                {{ $serverPort }}
            </div>
            <div class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                {{ $scheme === 'https' ? 'HTTPS Port (443)' : 'HTTP Port (' . $serverPort . ')' }}
            </div>
            <span
                class="sr-only"
                role="status"
                aria-live="polite"
                x-text="copied === 'server-port' ? 'Server port copied to clipboard' : (failed === 'server-port' ? 'Copy failed. Select the server port manually.' : '')"
            ></span>
        </div>

        <!-- Web Directory / Path -->
        <div class="rounded-lg bg-white p-3 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Web Path / Directory</span>
                <button type="button"
                        aria-label="Copy web path"
                        @click="copy(@js('/api'), 'web-path')"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 text-xs flex items-center gap-1 font-medium transition">
                    <span x-show="copied !== 'web-path' && failed !== 'web-path'">Copy</span>
                    <span x-show="copied === 'web-path'" x-cloak class="text-emerald-600 dark:text-emerald-400 font-semibold">Copied!</span>
                    <span x-show="failed === 'web-path'" x-cloak class="text-danger-600 dark:text-danger-400 font-semibold">Copy failed — select manually</span>
                </button>
            </div>
            <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-gray-100 select-all">
                /api
            </div>
            <div class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                Where supported by terminal firmware
            </div>
            <span
                class="sr-only"
                role="status"
                aria-live="polite"
                x-text="copied === 'web-path' ? 'Web path copied to clipboard' : (failed === 'web-path' ? 'Copy failed. Select the web path manually.' : '')"
            ></span>
        </div>

        <!-- Full Handshake URL -->
        <div class="sm:col-span-2 lg:col-span-2 rounded-lg bg-white p-3 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Full Protocol Handshake Endpoint</span>
                <button type="button"
                        aria-label="Copy full handshake endpoint"
                        @click="copy(@js($fullEndpoint), 'full-endpoint')"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 text-xs flex items-center gap-1 font-medium transition">
                    <span x-show="copied !== 'full-endpoint' && failed !== 'full-endpoint'">Copy URL</span>
                    <span x-show="copied === 'full-endpoint'" x-cloak class="text-emerald-600 dark:text-emerald-400 font-semibold">Copied!</span>
                    <span x-show="failed === 'full-endpoint'" x-cloak class="text-danger-600 dark:text-danger-400 font-semibold">Copy failed — select manually</span>
                </button>
            </div>
            <div class="mt-1 font-mono text-xs font-semibold text-gray-800 dark:text-gray-200 truncate select-all">
                {{ $fullEndpoint }}
            </div>
            <div class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                Terminal pushes attendance records to this URL — .aspx-suffixed URIs are also accepted.
            </div>
            <span
                class="sr-only"
                role="status"
                aria-live="polite"
                x-text="copied === 'full-endpoint' ? 'Full handshake endpoint copied to clipboard' : (failed === 'full-endpoint' ? 'Copy failed. Select the full handshake endpoint manually.' : '')"
            ></span>
        </div>

        <!-- Comm Key Reference -->
        <div class="rounded-lg bg-white p-3 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <span>Push Comm Key</span>
            </div>
            <div class="mt-1 text-xs font-semibold text-gray-900 dark:text-gray-100">
                Enter the key configured for this device
            </div>
            <div class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                New keys are exactly 6 characters. For a legacy device, enter the key exactly as it was provisioned.
            </div>
            <div class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                Status:
                <span class="font-semibold {{ str_starts_with($commKeyStatus, 'Unreadable') || str_starts_with($commKeyStatus, 'Unrecognized') ? 'text-danger-600 dark:text-danger-400' : (str_starts_with($commKeyStatus, 'Configured') ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400') }}">
                    {{ $commKeyStatus }}
                </span>
            </div>
        </div>
    </div>

    <!-- Step-by-Step Hardware Guide -->
    <div class="mt-4 rounded-lg bg-white/80 p-4 ring-1 ring-gray-950/5 dark:bg-gray-900/80 dark:ring-white/10">
        <h5 class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-300 flex items-center gap-1.5">
            <x-heroicon-m-device-phone-mobile class="h-4 w-4 text-primary-500" />
            Hardware Machine Setup Steps (eSSL / ZKTeco)
        </h5>
        <ol class="mt-2.5 space-y-1.5 text-xs text-gray-600 dark:text-gray-300 list-decimal list-inside">
            <li>Press <span class="font-semibold text-gray-900 dark:text-white">Menu (M/OK)</span> on the physical terminal.</li>
            <li>Go to <span class="font-semibold text-gray-900 dark:text-white">Comm. (Communication)</span> &rarr; <span class="font-semibold text-gray-900 dark:text-white">Cloud Server Setting</span> (or <span class="font-semibold text-gray-900 dark:text-white">ADMS</span>).</li>
            <li>Set <span class="font-semibold text-gray-900 dark:text-white">Server Mode</span> to <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-[11px] dark:bg-gray-800">ADMS</code>.</li>
            <li>Set <span class="font-semibold text-gray-900 dark:text-white">Enable Domain Name</span> to <span class="font-semibold text-emerald-600 dark:text-emerald-400">ON</span> (if using domain name) or <span class="font-semibold text-amber-600 dark:text-amber-400">OFF</span> (if using direct IP).</li>
            <li>Enter <span class="font-semibold text-gray-900 dark:text-white">Server Address</span>: <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-[11px] dark:bg-gray-800">{{ $serverHost }}</code>.</li>
            <li>Enter <span class="font-semibold text-gray-900 dark:text-white">Server Port</span>: <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-[11px] dark:bg-gray-800">{{ $serverPort }}</code>.</li>
            <li>Enter <span class="font-semibold text-gray-900 dark:text-white">Push Comm Key</span>: the ADMS Communication Key configured for this device — exactly 6 characters for a newly provisioned key (a 6-digit number is recommended; alphanumeric keys are supported), or the key exactly as it was provisioned for a legacy device.</li>
            <li>Save & restart device. Once connected, the cloud / globe icon on the terminal status bar will become active.</li>
        </ol>
    </div>
</div>
