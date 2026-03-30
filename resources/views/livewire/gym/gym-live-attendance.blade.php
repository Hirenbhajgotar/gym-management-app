<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                    <flux:icon name="arrow-right-end-on-rectangle" class="w-6 h-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Checked In</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $checkedInCount }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-red-100 dark:bg-red-900 rounded-full">
                    <flux:icon name="arrow-right-start-on-rectangle" class="w-6 h-6 text-red-600 dark:text-red-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Checked Out</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $checkedOutCount }}</p>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                    <flux:icon name="users" class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                </div>
                <div>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Currently Active</p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $activeCount }}</p>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-center gap-4">
                <flux:input
                    wire:model="search"
                    placeholder="Search members..."
                    icon="magnifying-glass"
                    class="max-w-xs"
                />
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Member</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Check-In</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Check-Out</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Duration</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Method</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($todayAttendances as $attendance)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <div class="flex-shrink-0 h-10 w-10 rounded-full bg-gray-200 dark:bg-gray-600 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-600 dark:text-gray-300">
                                            {{ strtoupper(substr($attendance->attendanceable?->name ?? 'U', 0, 1)) }}
                                        </span>
                                    </div>
                                    <div>
                                        <div class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $attendance->attendanceable?->name ?? 'Unknown' }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $attendance->check_in_time?->format('H:i:s') ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ $attendance->check_out_time?->format('H:i:s') ?? '-' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                @if($attendance->check_in_time && $attendance->check_out_time)
                                    {{ $attendance->check_in_time->diff($attendance->check_out_time)->format('%H:%i:%s') }}
                                @elseif($attendance->check_in_time)
                                    <span class="text-green-600 dark:text-green-400">Active</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @if($attendance->isBiometric())
                                    <flux:badge color="info" icon="finger-print">Biometric</flux:badge>
                                @else
                                    <flux:badge color="gray" icon="pencil">Manual</flux:badge>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                @switch($attendance->status?->value)
                                    @case('present')
                                        <flux:badge color="success">Present</flux:badge>
                                        @break
                                    @case('completed')
                                        <flux:badge color="gray">Completed</flux:badge>
                                        @break
                                    @case('absent')
                                        <flux:badge color="danger">Absent</flux:badge>
                                        @break
                                    @default
                                        <flux:badge color="warning">Pending</flux:badge>
                                @endswitch
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                No attendance records for today
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
