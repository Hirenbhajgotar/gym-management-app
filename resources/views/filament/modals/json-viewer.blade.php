<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $title }}
        </h3>
        <button
            type="button"
            onclick="copyJsonToClipboard()"
            class="inline-flex items-center gap-2 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            Copy JSON
        </button>
    </div>

    <div class="relative">
        <pre id="json-content" class="p-4 overflow-x-auto bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700"><code class="language-json text-sm">{{ json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
    </div>
</div>

<script>
    function copyJsonToClipboard() {
        const jsonContent = document.getElementById('json-content').textContent;
        navigator.clipboard.writeText(jsonContent).then(() => {
            // Show success notification
            if (window.$wireui) {
                window.$wireui.notify({
                    title: 'Success',
                    description: 'JSON copied to clipboard',
                    icon: 'success'
                });
            } else {
                alert('JSON copied to clipboard!');
            }
        }).catch(err => {
            console.error('Failed to copy:', err);
            alert('Failed to copy JSON');
        });
    }
</script>

<style>
    pre code {
        display: block;
        font-family: ui-monospace, SFMono-Regular, 'SF Mono', Menlo, Consolas, 'Liberation Mono', monospace;
        font-size: 0.875rem;
        line-height: 1.5;
        color: #374151;
    }

    .dark pre code {
        color: #d1d5db;
    }
</style>
