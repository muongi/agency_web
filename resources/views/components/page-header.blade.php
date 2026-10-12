@props([
    'title' => '',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'page-header']) }}>
    <div class="page-header__titles">
        <h1 class="page-header__title">{{ $title }}</h1>
        @if($subtitle)
            <p class="page-header__subtitle">{{ $subtitle }}</p>
        @endif
    </div>
    @if(isset($actions) || !$slot->isEmpty())
        <div class="page-header__actions">
            {{ $actions ?? $slot }}
        </div>
    @endif
</div>
