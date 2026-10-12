{{-- Append-only history of one correction request: who, when, what, values before → after. --}}
<table class="table" style="max-width:960px;">
    <thead>
        <tr>
            <th>{{ __('common.date') }}</th>
            <th>{{ __('common.sunu_corr_event') }}</th>
            <th>{{ __('common.sunu_corr_by') }}</th>
            <th>{{ __('common.sunu_corr_note') }}</th>
            <th>{{ __('common.sunu_corr_changes') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($events as $ev)
            <tr>
                <td style="white-space:nowrap;">{{ optional($ev->created_at)->format('d/m/Y H:i:s') }}</td>
                <td>{{ __('common.sunu_corr_event_'.$ev->event) }}</td>
                <td>
                    {{ $ev->actor_name ?: '—' }}
                    <div style="font-size:12px; color:var(--text-muted);">{{ __('common.sunu_corr_actor_'.$ev->actor_type) }}</div>
                </td>
                <td>{{ $ev->note ?: '—' }}</td>
                <td style="font-size:13px;">
                    @php
                        $before = $ev->before ?? [];
                        $after = $ev->after ?? [];
                        $keys = $ev->event === 'requested' ? array_keys($after) : array_keys(array_merge($before, $after));
                    @endphp
                    @foreach($keys as $k)
                        @if($ev->event === 'requested')
                            <div>{{ __('common.sunu_corr_field_'.$k) }} → <strong>{{ $after[$k] }}</strong></div>
                        @elseif(($before[$k] ?? null) != ($after[$k] ?? null))
                            <div>{{ __('common.sunu_corr_field_'.$k) }} : {{ $before[$k] ?? '—' }} → <strong>{{ $after[$k] ?? '—' }}</strong></div>
                        @endif
                    @endforeach
                </td>
            </tr>
        @empty
            <tr><td colspan="5">—</td></tr>
        @endforelse
    </tbody>
</table>
