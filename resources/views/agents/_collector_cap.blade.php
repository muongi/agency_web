{{-- SUNU collection limit of this agent (TDR: limit reached → deposit → office releases the TPE) --}}
@php
    $canManageCap = auth()->user()->hasPermission('manage-sunu-collector-caps', $agent->business_unit_id);
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp
<div style="margin-top: 30px; padding: 15px; background: var(--bg-tertiary); border-radius: 4px; border: 1px solid var(--border-color);">
    <h2 class="text-accent" style="margin-bottom: 6px;">{{ __('common.collector_cap') }}</h2>
    <p class="muted" style="margin-bottom: 15px; font-size: 13px;">{{ __('common.collector_cap_help') }}</p>

    <p style="margin-bottom: 6px;">
        {{ __('common.collector_held') }} : <strong>{{ $fmt($collector['uncollected_amount']) }} XOF</strong>
        @if($collector['cap_amount'] !== null)
            / {{ __('common.collector_cap_amount') }} <strong>{{ $fmt($collector['cap_amount']) }} XOF</strong>
            ({{ $collector['cap_source'] === 'agent' ? __('common.collector_cap_source_agent') : __('common.collector_cap_source_default') }})
            — {{ __('common.collector_remaining') }} <strong>{{ $fmt($collector['remaining_amount']) }} XOF</strong>
        @else
            — {{ __('common.collector_cap_none') }}
        @endif
    </p>
    <p style="margin-bottom: 12px;">
        @include('business.sunu.payins._collector_state', ['status' => $collector])
        @if($collector['last_pay_in_at'])
            <span class="muted" style="font-size: 12px;">· {{ __('common.collector_last_deposit') }} {{ \Carbon\Carbon::parse($collector['last_pay_in_at'])->format('Y-m-d H:i') }}</span>
        @endif
    </p>

    @if($canManageCap)
        <form method="POST" action="{{ route('agents.collector-cap', $agent->id) }}" style="margin-bottom: 12px;">
            @csrf
            <label style="display: block; margin-bottom: 8px;">
                <input type="radio" name="mode" value="default" {{ $agentCap ? '' : 'checked' }}>
                {{ __('common.collector_cap_use_default') }}
                <span class="muted" style="font-size: 12px;">
                    ({{ $agent->businessUnit?->sunu_collector_cap_amount ? $fmt($agent->businessUnit->sunu_collector_cap_amount).' XOF' : __('common.collector_cap_none') }})
                </span>
            </label>
            <label style="display: block; margin-bottom: 8px;">
                <input type="radio" name="mode" value="custom" {{ $agentCap ? 'checked' : '' }}>
                {{ __('common.collector_cap_custom') }}
            </label>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin: 0 0 12px 22px;">
                <input type="number" name="cap_amount" min="1" step="1" class="form-input" style="max-width: 180px;"
                       value="{{ old('cap_amount', $agentCap ? (int) $agentCap->cap_amount : '') }}" placeholder="{{ __('common.collector_cap_amount') }} (XOF)">
                <input type="number" name="warning_pct" min="1" max="100" step="1" class="form-input" style="max-width: 120px;"
                       value="{{ old('warning_pct', $agentCap->warning_threshold_pct ?? 80) }}" title="{{ __('common.collector_warning_pct') }}">
                <span class="muted" style="font-size: 12px; align-self: center;">% {{ __('common.collector_warning_pct') }}</span>
            </div>
            @error('cap_amount')<p style="color: var(--danger-color, #c0392b); font-size: 12px;">{{ $message }}</p>@enderror
            <button type="submit" class="btn btn-sm">{{ __('common.save') }}</button>
        </form>

        @if($collector['is_blocked'])
            <form method="POST" action="{{ route('business.sunu.collectors.release', $agent->id) }}" style="display: flex; gap: 8px; flex-wrap: wrap;">
                @csrf
                <input type="text" name="note" required maxlength="1000" class="form-input" style="flex: 1; min-width: 220px;" placeholder="{{ __('common.collector_release_note') }}">
                <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('{{ __('common.collector_release_confirm') }}')">{{ __('common.collector_release') }}</button>
            </form>
        @endif
    @endif
</div>
