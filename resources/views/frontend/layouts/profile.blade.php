{{-- Deprecated: Unreferenced legacy layout. ProfileController uses frontend.layouts.app_new --}}
<x-frontend.layouts.app_new :title="$title ?? config('app.name')">
    {{ $slot }}
</x-frontend.layouts.app_new>
