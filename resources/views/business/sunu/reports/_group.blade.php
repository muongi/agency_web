{{-- $title, $items (label, count, amount?) , $money (bool) --}}
<div style="flex:1;min-width:260px;">
    <h3 style="color:var(--accent-color);margin:0 0 8px;">{{ $title }}</h3>
    <div class="data-table">
        <table>
            <thead><tr>
                <th></th>
                <th style="text-align:right;">{{ __('common.sunu_report_count') }}</th>
                @if($money ?? true)<th style="text-align:right;">{{ __('common.amount') }}</th>@endif
            </tr></thead>
            <tbody>
                @forelse($items as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td style="text-align:right;">{{ $row['count'] }}</td>
                        @if($money ?? true)<td style="text-align:right;">{{ number_format($row['amount'], 0, ',', ' ') }}</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted">{{ __('common.sunu_report_no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
