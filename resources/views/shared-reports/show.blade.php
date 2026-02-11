<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} - {{ $link->gym->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    @if($link->gym->logo)
                        <img src="{{ Storage::url($link->gym->logo) }}" alt="{{ $link->gym->name }}" class="h-10 w-10 rounded-lg object-cover">
                    @else
                        <div class="h-10 w-10 bg-indigo-600 rounded-lg flex items-center justify-center">
                            <span class="text-white font-semibold">{{ substr($link->gym->name, 0, 1) }}</span>
                        </div>
                    @endif
                    <div>
                        <h1 class="text-xl font-semibold text-gray-900">{{ $title }}</h1>
                        <p class="text-sm text-gray-500">{{ $link->gym->name }}</p>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Generated</p>
                        <p class="text-sm font-medium text-gray-900">{{ $generatedAt->format('M j, Y g:i A') }}</p>
                    </div>
                    @if($link->expires_at)
                        <div class="text-right">
                            <p class="text-sm text-gray-500">Expires</p>
                            <p class="text-sm font-medium text-orange-600">{{ $link->expires_at->format('M j, Y') }}</p>
                        </div>
                    @endif
                    <div x-data>
                        <button 
                            @click="$clipboard.copy('{{ url()->current() }}')"
                            type="button"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            <svg class="-ml-1 mr-2 h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z" />
                                <path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z" />
                            </svg>
                            Copy Link
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Report Warning -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-8">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-blue-800">
                        This is a shared read-only report. Data reflects the period when this report was generated.
                    </p>
                </div>
            </div>
        </div>

        <!-- Report Content -->
        <div class="space-y-8">
            @switch($link->report_type)
                @case(\App\Filament\Admin\Clusters\Reports\Pages\ReportsDashboard::class)
                    @include('shared-reports.dashboard')
                    @break

                @case(\App\Filament\Admin\Clusters\Reports\Pages\RevenueReports::class)
                    @include('shared-reports.revenue')
                    @break

                @case(\App\Filament\Admin\Clusters\Reports\Pages\MemberReports::class)
                    @include('shared-reports.members')
                    @break

                @case(\App\Filament\Admin\Clusters\Reports\Pages\LeadReports::class)
                    @include('shared-reports.leads')
                    @break

                @case(\App\Filament\Admin\Clusters\Reports\Pages\AttendanceReports::class)
                    @include('shared-reports.attendance')
                    @break

                @case(\App\Filament\Admin\Clusters\Reports\Pages\TrainerReports::class)
                    @include('shared-reports.trainers')
                    @break

                @case(\App\Filament\Admin\Clusters\Reports\Pages\EquipmentUsageReports::class)
                    @include('shared-reports.equipment')
                    @break

                @case(\App\Filament\Admin\Clusters\Reports\Pages\MemberLifetimeValueReports::class)
                    @include('shared-reports.ltv')
                    @break

                @case(\App\Filament\Admin\Clusters\Reports\Pages\ExpenseReportsCluster::class)
                    @include('shared-reports.expenses')
                    @break

                @default
                    @include('shared-reports.generic')
            @endswitch
        </div>
    </div>

    <!-- Footer -->
    <div class="bg-white border-t border-gray-200 mt-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="text-center text-sm text-gray-500">
                <p>Shared report powered by Gym Management Platform</p>
                <p class="mt-1">Generated on {{ $generatedAt->format('F j, Y \a\t g:i A') }}</p>
            </div>
        </div>
    </div>

    <!-- Copy to Clipboard Script -->
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.magic('clipboard', () => {
                return {
                    copy: function(text) {
                        navigator.clipboard.writeText(text).then(() => {
                            // Show success message
                            const toast = document.createElement('div');
                            toast.className = 'fixed top-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                            toast.textContent = 'Copied to clipboard!';
                            document.body.appendChild(toast);
                            
                            setTimeout(() => {
                                document.body.removeChild(toast);
                            }, 2000);
                        });
                    }
                }
            });
        });

        // Listen for copy-to-clipboard events
        window.addEventListener('copy-to-clipboard', (event) => {
            if (window.Alpine && window.Alpine.store) {
                window.Alpine.clipboard().copy(event.detail.text);
            } else {
                // Fallback if Alpine is not available
                navigator.clipboard.writeText(event.detail.text);
            }
        });
    </script>
</body>
</html>