@php
    $appUrl = config('app.url');
    $parsedUrl = parse_url($appUrl ?: 'http://localhost');
    $serverHost = $parsedUrl['host'] ?? request()->getHost();
    $scheme = $parsedUrl['scheme'] ?? (request()->isSecure() ? 'https' : 'http');
    $serverPort = $parsedUrl['port'] ?? ($scheme === 'https' ? '443' : '80');
    $fullEndpoint = rtrim($appUrl ?: request()->root(), '/') . '/api/iclock/cdata';
    $commKey = $commKey ?? null;
@endphp

<div class="rounded-xl border border-primary-500/20 bg-primary-50/50 p-5 dark:border-primary-500/30 dark:bg-primary-950/20 text-sm">
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
        <div class="rounded-lg bg-white p-3 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
             x-data="{ copied: false }">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Server Address / Domain</span>
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $serverHost }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 text-xs flex items-center gap-1 font-medium transition">
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak class="text-emerald-600 dark:text-emerald-400 font-semibold">Copied!</span>
                </button>
            </div>
            <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-gray-100 select-all">
                {{ $serverHost }}
            </div>
            <div class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                Enter without http:// or path
            </div>
        </div>

        <!-- Server Port -->
        <div class="rounded-lg bg-white p-3 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
             x-data="{ copied: false }">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Server Port</span>
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $serverPort }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 text-xs flex items-center gap-1 font-medium transition">
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak class="text-emerald-600 dark:text-emerald-400 font-semibold">Copied!</span>
                </button>
            </div>
            <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-gray-100 select-all">
                {{ $serverPort }}
            </div>
            <div class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                {{ $scheme === 'https' ? 'HTTPS Port (443)' : 'HTTP Port (' . $serverPort . ')' }}
            </div>
        </div>

        <!-- Web Directory / Path -->
        <div class="rounded-lg bg-white p-3 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
             x-data="{ copied: false }">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Web Path / Directory</span>
                <button type="button"
                        @click="navigator.clipboard.writeText('/api'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 text-xs flex items-center gap-1 font-medium transition">
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak class="text-emerald-600 dark:text-emerald-400 font-semibold">Copied!</span>
                </button>
            </div>
            <div class="mt-1 font-mono text-sm font-semibold text-gray-900 dark:text-gray-100 select-all">
                /api
            </div>
            <div class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                Where supported by terminal firmware
            </div>
        </div>

        <!-- Full Handshake URL -->
        <div class="sm:col-span-2 lg:col-span-2 rounded-lg bg-white p-3 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
             x-data="{ copied: false }">
            <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                <span>Full Protocol Handshake Endpoint</span>
                <button type="button"
                        @click="navigator.clipboard.writeText('{{ $fullEndpoint }}'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="text-primary-600 hover:text-primary-500 dark:text-primary-400 text-xs flex items-center gap-1 font-medium transition">
                    <span x-show="!copied">Copy URL</span>
                    <span x-show="copied" x-cloak class="text-emerald-600 dark:text-emerald-400 font-semibold">Copied!</span>
                </button>
            </div>
            <div class="mt-1 font-mono text-xs font-semibold text-gray-800 dark:text-gray-200 truncate select-all">
                {{ $fullEndpoint }}
            </div>
            <div class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                Terminal pushes attendance records to this URL
            </div>
        </div>

        <!-- Comm Key Reference -->
        <div class="rounded-lg bg-white p-3 shadow-xs ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="text-xs text-gray-500 dark:text-gray-400">
                <span>Push Comm Key</span>
            </div>
            <div class="mt-1 font-mono text-xs font-semibold text-gray-900 dark:text-gray-100">
                @if(filled($commKey))
                    <span class="truncate block select-all">{{ $commKey }}</span>
                @else
                    <span class="text-amber-600 dark:text-amber-400 font-sans">Must match field above</span>
                @endif
            </div>
            <div class="mt-0.5 text-[11px] text-gray-400 dark:text-gray-500">
                Authentication key for push requests
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
            <li>Enter <span class="font-semibold text-gray-900 dark:text-white">Push Comm Key</span>: Must match the ADMS Communication Key set on this page.</li>
            <li>Save & restart device. Once connected, the cloud / globe icon on the terminal status bar will become active.</li>
        </ol>
    </div>
</div>
