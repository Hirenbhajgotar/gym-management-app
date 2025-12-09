@props(['variant' => 'icon', 'class' => ''])

@php
    $sizeClasses = match($variant) {
        'full' => 'h-20 w-auto',
        'icon' => 'h-8 w-8',
        default => 'h-8 w-8'
    };
@endphp

@if($variant === 'full')
    {{-- Full Logo with Text --}}
    <svg {{ $attributes->class([$sizeClasses, $class]) }} viewBox="0 0 200 50" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="fluxGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#3B82F6;stop-opacity:1" />
                <stop offset="50%" style="stop-color:#6366F1;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#8B5CF6;stop-opacity:1" />
            </linearGradient>
            <filter id="glow">
                <feGaussianBlur stdDeviation="2" result="coloredBlur"/>
                <feMerge>
                    <feMergeNode in="coloredBlur"/>
                    <feMergeNode in="SourceGraphic"/>
                </feMerge>
            </filter>
        </defs>
        
        {{-- Icon Part --}}
        <g filter="url(#glow)">
            {{-- Stylized F with Dumbbell --}}
            <path d="M 8 8 L 8 42 L 12 42 L 12 28 L 24 28 L 24 24 L 12 24 L 12 12 L 28 12 L 28 8 Z" fill="url(#fluxGradient)"/>
            
            {{-- Dumbbell Weight (Left) --}}
            <circle cx="10" cy="18" r="3" fill="url(#fluxGradient)"/>
            
            {{-- Dumbbell Bar --}}
            <rect x="10" y="17" width="14" height="2" fill="url(#fluxGradient)" rx="1"/>
            
            {{-- Dumbbell Weight (Right) --}}
            <circle cx="24" cy="18" r="3" fill="url(#fluxGradient)"/>
            
            {{-- Strength Lines --}}
            <path d="M 30 14 L 34 10" stroke="url(#fluxGradient)" stroke-width="2" stroke-linecap="round"/>
            <path d="M 30 22 L 34 26" stroke="url(#fluxGradient)" stroke-width="2" stroke-linecap="round"/>
        </g>
        
        {{-- Text --}}
        <text x="44" y="32" font-family="Arial, sans-serif" font-size="18" font-weight="bold" fill="currentColor">
            {{ config('app.name') }}
        </text>
    </svg>
@else
    {{-- Icon Only --}}
    <svg {{ $attributes->class([$sizeClasses, $class]) }} viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="fluxGradientIcon" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" style="stop-color:#3B82F6;stop-opacity:1" />
                <stop offset="50%" style="stop-color:#6366F1;stop-opacity:1" />
                <stop offset="100%" style="stop-color:#8B5CF6;stop-opacity:1" />
            </linearGradient>
            <filter id="glowIcon">
                <feGaussianBlur stdDeviation="1.5" result="coloredBlur"/>
                <feMerge>
                    <feMergeNode in="coloredBlur"/>
                    <feMergeNode in="SourceGraphic"/>
                </feMerge>
            </filter>
        </defs>
        
        <g filter="url(#glowIcon)">
            {{-- Stylized F with Dumbbell --}}
            <path d="M 6 6 L 6 34 L 10 34 L 10 22 L 24 22 L 24 18 L 10 18 L 10 10 L 28 10 L 28 6 Z" fill="url(#fluxGradientIcon)"/>
            
            {{-- Dumbbell Weight (Left) --}}
            <circle cx="8" cy="14" r="2.5" fill="url(#fluxGradientIcon)"/>
            
            {{-- Dumbbell Bar --}}
            <rect x="8" y="13" width="14" height="2" fill="url(#fluxGradientIcon)" rx="1"/>
            
            {{-- Dumbbell Weight (Right) --}}
            <circle cx="22" cy="14" r="2.5" fill="url(#fluxGradientIcon)"/>
            
            {{-- Strength Lines --}}
            <path d="M 30 10 L 34 6" stroke="url(#fluxGradientIcon)" stroke-width="2" stroke-linecap="round"/>
            <path d="M 30 18 L 34 22" stroke="url(#fluxGradientIcon)" stroke-width="2" stroke-linecap="round"/>
            <path d="M 30 14 L 34 14" stroke="url(#fluxGradientIcon)" stroke-width="2" stroke-linecap="round"/>
        </g>
    </svg>
@endif
