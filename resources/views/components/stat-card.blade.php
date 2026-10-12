@props([
    'label' => '',
    'value' => '',
    'icon' => 'chart',
    'href' => null,
])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'stat-card']) }}>
    <span class="stat-card__icon"><x-icon :name="$icon" :size="22" /></span>
    <span class="stat-card__body">
        <span class="stat-card__label">{{ $label }}</span>
        <span class="stat-card__value">{{ $value }}</span>
        {{ $slot }}
    </span>
</{{ $tag }}>
