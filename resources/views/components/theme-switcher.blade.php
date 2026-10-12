@php
    $currentTheme = session('theme', 'dark');
@endphp
<form action="{{ route('theme.switch') }}" method="POST" style="display: inline;">
    @csrf
    <input type="hidden" name="theme" value="{{ $currentTheme === 'dark' ? 'light' : 'dark' }}">
    <button type="submit" class="theme-toggle-btn" title="{{ $currentTheme === 'dark' ? __('common.switch_to_light') : __('common.switch_to_dark') }}">
        @if($currentTheme === 'dark')
            <x-icon name="sun" :size="18" />
        @else
            <x-icon name="moon" :size="18" />
        @endif
    </button>
</form>

