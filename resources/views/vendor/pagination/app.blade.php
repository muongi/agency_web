{{-- App-wide pagination (no Tailwind / SVG: the default view's arrow icons have no size here and fill the page). --}}
@if ($paginator->hasPages())
<nav class="app-pagination" role="navigation" aria-label="Pagination">
    <style>
        .app-pagination { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; margin:16px 0 4px; font-size:13px; }
        .app-pagination .pg-info { color:var(--text-muted, #8a8f98); }
        .app-pagination ul { display:flex; flex-wrap:wrap; gap:4px; list-style:none; margin:0; padding:0; }
        .app-pagination li > a, .app-pagination li > span { display:inline-block; min-width:34px; padding:6px 10px; text-align:center; line-height:1.2;
            border:1px solid var(--border-color, #d0d4da); border-radius:6px; text-decoration:none; color:inherit; }
        .app-pagination li > a:hover { border-color:var(--accent-color, #c8102e); color:var(--accent-color, #c8102e); }
        .app-pagination li.active > span { background:var(--accent-color, #c8102e); border-color:var(--accent-color, #c8102e); color:#fff; font-weight:600; }
        .app-pagination li.disabled > span { opacity:.45; }
    </style>
    @if (method_exists($paginator, 'total'))
        <div class="pg-info">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} / {{ $paginator->total() }}</div>
    @endif
    <ul>
        @if ($paginator->onFirstPage())
            <li class="disabled" aria-disabled="true"><span>&lsaquo;</span></li>
        @else
            <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="{{ strip_tags(__('pagination.previous')) }}">&lsaquo;</a></li>
        @endif

        @isset($elements)
            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="disabled" aria-disabled="true"><span>{{ $element }}</span></li>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="active" aria-current="page"><span>{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach
        @endisset

        @if ($paginator->hasMorePages())
            <li><a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="{{ strip_tags(__('pagination.next')) }}">&rsaquo;</a></li>
        @else
            <li class="disabled" aria-disabled="true"><span>&rsaquo;</span></li>
        @endif
    </ul>
</nav>
@endif
