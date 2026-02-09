@props(['data', 'color' => 'primary'])

@php
    if (empty($data) || !is_array($data)) {
        $data = [];
    }
    
    $values = array_values($data);
    if (count($values) < 2) {
        // Not enough data for sparkline
        echo '';
        return;
    }
    
    $max = max($values) ?: 1;
    $min = min($values) ?: 0;
    $range = $max - $min ?: 1;
    $points = [];
    $width = 200;
    $height = 40;
    
    foreach ($values as $i => $value) {
        $x = ($i / (count($values) - 1)) * $width;
        $y = $height - ((($value - $min) / $range) * $height);
        $points[] = "{$x},{$y}";
    }
    
    $pathData = 'M ' . implode(' L ', $points);
@endphp

<svg class="h-10 w-full" viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none">
    <path 
        d="{{ $pathData }}" 
        fill="none" 
        stroke="currentColor" 
        stroke-width="2" 
        class="text-{{ $color }}-500"
    />
</svg>
