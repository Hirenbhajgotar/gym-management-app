@props(['variant' => 'icon', 'class' => ''])

@php
    $sizeClasses = match($variant) {
        'full' => 'h-14 w-auto',
        'icon' => 'h-9 w-9',
        default => 'h-9 w-9'
    };

    $finalClasses = $class ?: $sizeClasses;
@endphp

@if($variant === 'full')
    {{-- Full Logo with Text --}}
    <img src="{{ asset('fluxfitness-logo.svg') }}" alt="Flux Fitness Logo" {{ $attributes->class([$finalClasses]) }}>
@else
    {{-- Icon Only --}}
    <img src="{{ asset('fluxfitness-icon.png') }}" alt="Flux Fitness Logo" {{ $attributes->class([$finalClasses]) }}>
@endif
