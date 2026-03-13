@if (auth()->check() && !request()->routeIs(['filament.*.auth.*', 'login', 'password.*', 'register', 'gym.register', 'filament.*.tenant.*']))
    <div style="position: fixed; bottom: 24px; right: 24px; z-index: 9999;">
        <livewire:feedback-trigger />
    </div>
@endif
