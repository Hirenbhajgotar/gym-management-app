@if (auth()->check())
    <div style="position: fixed; bottom: 24px; right: 24px; z-index: 9999;">
        <livewire:feedback-trigger />
    </div>
@endif
