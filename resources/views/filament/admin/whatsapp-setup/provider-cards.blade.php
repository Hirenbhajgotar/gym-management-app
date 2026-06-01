<div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-4">
    <div class="p-4 rounded-lg border-2 {{ $selectedProvider === 'waba' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700' }}">
        <div class="flex items-center justify-between mb-2">
            <span class="font-semibold {{ $selectedProvider === 'waba' ? 'text-primary-900 dark:text-primary-400' : 'text-gray-900 dark:text-gray-100' }}">Meta WABA</span>
            @if($selectedProvider === 'waba') <span class="text-primary-900 dark:text-primary-400">✓ Selected</span> @endif
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Connect your existing WhatsApp Business number using Meta's embedded signup.</p>
        <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1"><li>• Use your own number</li><li>• Embedded signup flow</li><li>• Lower costs</li></ul>
    </div>
    <div class="p-4 rounded-lg border-2 {{ $selectedProvider === 'twilio' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : 'border-gray-200 dark:border-gray-700' }}">
        <div class="flex items-center justify-between mb-2">
            <span class="font-semibold {{ $selectedProvider === 'twilio' ? 'text-primary-900 dark:text-primary-400' : 'text-gray-900 dark:text-gray-100' }}">Twilio</span>
            @if($selectedProvider === 'twilio') <span class="text-primary-900 dark:text-primary-400">✓ Selected</span> @endif
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Purchase a dedicated WhatsApp number through Twilio. We'll create a subaccount for your gym.</p>
        <ul class="text-sm text-gray-500 dark:text-gray-400 space-y-1"><li>• Dedicated phone number</li><li>• Full WhatsApp API access</li><li>• Subaccount isolation</li></ul>
    </div>
</div>