{{-- $tiles = [[label, value, sub?, url?], ...] --}}
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:24px;">
    @foreach($tiles as $t)
        @php $tag = !empty($t[3]) ? 'a' : 'div'; @endphp
        <{{ $tag }} @if(!empty($t[3])) href="{{ $t[3] }}" @endif style="display:block;padding:16px;background:var(--bg-tertiary);border-radius:8px;text-decoration:none;color:inherit;">
            <div class="muted" style="font-size:13px;">{{ $t[0] }}</div>
            <div style="font-size:22px;font-weight:700;color:var(--accent-color);margin-top:4px;">{{ $t[1] }}</div>
            @if(!empty($t[2]))<div class="muted" style="font-size:12px;margin-top:2px;">{{ $t[2] }}</div>@endif
        </{{ $tag }}>
    @endforeach
</div>
