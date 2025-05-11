<div class="flex items-center space-x-2">
    @php
        $state = $getState();
        $members = $state['members'];
        $membersCount = $state['members_count'];
    @endphp

    @if ($members->isEmpty())
        <span class="text-gray-500">No members assigned</span>
    @else
        <div class="flex -space-x-3">
            @foreach ($members as $member)
                @if ($member->profile_photo)
                    <img src="{{ url('storage/' . $member->profile_photo) }}" alt="{{ $member->name }}"
                        class="w-8 h-8 rounded-full border-1 border-white">
                @else
                    <img src="https://ui-avatars.com/api/?name={{ urlencode($member->name) }}&background=random&color=000"
                        alt="{{ $member->name }}" class="w-8 h-8 rounded-full border-1 border-white">
                @endif
            @endforeach
            <img src="https://ui-avatars.com/api/?name={{ urlencode('+' . $membersCount) }}&background=2e2d2de0&color=fff"
                alt="{{ $membersCount }}" class="w-8 h-8 rounded-full border-1 border-white">
        </div>
        {{-- @if ($membersCount > 3)
            <span class="text-sm text-gray-500">+{{ $membersCount - 3 }} more</span>
        @endif --}}
        {{-- <span class="text-sm text-gray-500">(+{{ $membersCount }})</span> --}}
    @endif
</div>
