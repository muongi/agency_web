@extends('layouts.app')

@section('title', __('common.sunu_payins_title'))
@section('page-title', __('common.sunu_payins_heading'))

@php
    $canValidate = auth()->user()->hasPermission('validate-sunu-payins', $bu->id);
    $canManageCap = auth()->user()->hasPermission('manage-sunu-collector-caps', $bu->id);
    $fmt = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $methodLabel = fn ($m) => $m ? __('common.sunu_payin_method_'.$m) : '—';
@endphp

@section('content')
{{-- 1. Cash held by each collector against the collection limit --}}
<div class="card">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
        <div>
            <h1 style="margin:0;color:var(--accent-color);">{{ __('common.collector_cash_title') }}</h1>
            <p class="muted" style="font-size:13px;margin:4px 0 0;">{{ __('common.collector_cash_help') }}</p>
        </div>
        @if($canManageCap)
            <form method="POST" action="{{ route('business.sunu.collectors.cap-default') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                @csrf
                <label style="font-size:13px;">{{ __('common.collector_cap_default') }}</label>
                <input type="number" name="cap_amount" min="0" step="1" class="form-input" style="width:150px;"
                       value="{{ old('cap_amount', $bu->sunu_collector_cap_amount !== null ? (int) $bu->sunu_collector_cap_amount : '') }}" placeholder="{{ __('common.collector_cap_none') }}">
                <span style="font-size:13px;">XOF · {{ __('common.collector_warning_pct') }}</span>
                <input type="number" name="warning_pct" min="1" max="100" step="1" class="form-input" style="width:70px;"
                       value="{{ old('warning_pct', $bu->sunu_collector_cap_warning_pct ?? 80) }}">
                <span style="font-size:13px;">%</span>
                <button class="btn btn-sm" type="submit">{{ __('common.save') }}</button>
            </form>
        @else
            <p style="font-size:13px;">{{ __('common.collector_cap_default') }} :
                <strong>{{ $bu->sunu_collector_cap_amount !== null ? $fmt($bu->sunu_collector_cap_amount).' XOF' : __('common.collector_cap_none') }}</strong></p>
        @endif
    </div>

    <div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('common.agent') }}</th>
                <th>{{ __('common.collector_held') }}</th>
                <th>{{ __('common.collector_cap_amount') }}</th>
                <th>{{ __('common.collector_remaining') }}</th>
                <th>{{ __('common.status') }}</th>
                <th>{{ __('common.collector_last_deposit') }}</th>
                <th>{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($collectors as $c)
                @php $s = $c['status']; @endphp
                <tr>
                    <td><a href="{{ route('agents.show', ['agent' => $c['agent']->id, 'tab' => 'terminals']) }}">{{ $c['agent']->full_name }}</a></td>
                    <td><strong>{{ $fmt($s['uncollected_amount']) }}</strong> XOF</td>
                    <td>
                        @if($s['cap_amount'] !== null)
                            {{ $fmt($s['cap_amount']) }} XOF
                            <div class="muted" style="font-size:11px;">{{ $s['cap_source'] === 'agent' ? __('common.collector_cap_source_agent') : __('common.collector_cap_source_default') }} · {{ $s['utilisation_pct'] }} %</div>
                        @else
                            <span class="muted">{{ __('common.collector_cap_none') }}</span>
                        @endif
                    </td>
                    <td>{{ $s['remaining_amount'] !== null ? $fmt($s['remaining_amount']).' XOF' : '—' }}</td>
                    <td>@include('business.sunu.payins._collector_state', ['status' => $s])</td>
                    <td>{{ $s['last_pay_in_at'] ? \Carbon\Carbon::parse($s['last_pay_in_at'])->format('Y-m-d H:i') : '—' }}</td>
                    <td style="white-space:nowrap;">
                        @if($canValidate && $s['uncollected_amount'] > 0)
                            <a class="btn btn-sm" href="#record-deposit" onclick="sunuPrefillDeposit({{ $c['agent']->id }}, {{ (int) round($s['uncollected_amount']) }})">{{ __('common.sunu_payin_record') }}</a>
                        @endif
                        @if($canManageCap && $s['is_blocked'])
                            <details style="display:inline-block;">
                                <summary class="btn btn-secondary btn-sm" style="list-style:none;cursor:pointer;">{{ __('common.collector_release') }}</summary>
                                <form method="POST" action="{{ route('business.sunu.collectors.release', $c['agent']->id) }}" style="margin-top:6px;display:flex;gap:6px;">
                                    @csrf
                                    <input type="text" name="note" required maxlength="1000" class="form-input" placeholder="{{ __('common.collector_release_note') }}">
                                    <button class="btn btn-secondary btn-sm" type="submit">OK</button>
                                </form>
                            </details>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7">{{ __('common.no_agents_found') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>

{{-- 2. Deposit recorded by the office (bank slip / cash-desk receipt) --}}
@if($canValidate)
<div class="card" id="record-deposit" style="margin-top:20px;">
    <h2 style="margin-top:0;color:var(--accent-color);">{{ __('common.sunu_payin_record_title') }}</h2>
    <p class="muted" style="font-size:13px;">{{ __('common.sunu_payin_record_help') }}</p>
    <form method="POST" action="{{ route('business.sunu.payins.store') }}" enctype="multipart/form-data">
        @csrf
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;">
            <div>
                <label class="form-label">{{ __('common.agent') }} *</label>
                <select name="agent_id" id="deposit-agent" class="form-select" required style="width:100%;">
                    <option value="">—</option>
                    @foreach($collectors as $c)
                        <option value="{{ $c['agent']->id }}" data-held="{{ (int) round($c['status']['uncollected_amount']) }}" @selected(old('agent_id') == $c['agent']->id)>
                            {{ $c['agent']->full_name }} ({{ $fmt($c['status']['uncollected_amount']) }} XOF)
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">{{ __('common.amount') }} (XOF) *</label>
                <input type="number" name="amount" id="deposit-amount" min="1" step="1" required class="form-input" style="width:100%;" value="{{ old('amount') }}">
            </div>
            <div>
                <label class="form-label">{{ __('common.sunu_method') }} *</label>
                <select name="method" class="form-select" required style="width:100%;">
                    <option value="bank_deposit" @selected(old('method', 'bank_deposit') === 'bank_deposit')>{{ __('common.sunu_payin_method_bank_deposit') }}</option>
                    <option value="cash_office" @selected(old('method') === 'cash_office')>{{ __('common.sunu_payin_method_cash_office') }}</option>
                </select>
            </div>
            <div>
                <label class="form-label">{{ __('common.sunu_payin_deposited_at') }} *</label>
                <input type="date" name="deposited_at" required class="form-input" style="width:100%;" max="{{ now()->toDateString() }}" value="{{ old('deposited_at', now()->toDateString()) }}">
            </div>
            <div>
                <label class="form-label">{{ __('common.sunu_payin_bank_reference') }}</label>
                <input type="text" name="bank_reference" maxlength="128" class="form-input" style="width:100%;" value="{{ old('bank_reference') }}" placeholder="{{ __('common.sunu_payin_bank_reference_hint') }}">
            </div>
            <div>
                <label class="form-label">{{ __('common.sunu_payin_bank_name') }}</label>
                <input type="text" name="bank_name" maxlength="128" class="form-input" style="width:100%;" value="{{ old('bank_name') }}">
            </div>
            <div>
                <label class="form-label">{{ __('common.sunu_payin_bank_account') }}</label>
                <input type="text" name="bank_account" maxlength="64" class="form-input" style="width:100%;" value="{{ old('bank_account') }}">
            </div>
            <div>
                <label class="form-label">{{ __('common.sunu_payin_slip') }}</label>
                <input type="file" name="deposit_slip" accept="image/*,application/pdf" class="form-input" style="width:100%;">
            </div>
        </div>
        <div style="margin-top:12px;">
            <label class="form-label">{{ __('common.notes') }}</label>
            <textarea name="notes" rows="2" maxlength="1000" class="form-input" style="width:100%;">{{ old('notes') }}</textarea>
        </div>
        @if($errors->any())
            <div style="color:#b3261e;font-size:13px;margin-top:8px;">
                @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
            </div>
        @endif
        <button class="btn" type="submit" style="margin-top:12px;">{{ __('common.sunu_payin_record_submit') }}</button>
    </form>
</div>
@endif

{{-- 3. All deposits: declared on the TPE or recorded here --}}
<div class="card" style="margin-top:20px;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:12px;">
        <h2 style="margin:0;color:var(--accent-color);">{{ __('common.sunu_payins_title') }}</h2>
        <div style="display:flex;gap:8px;">
            <a class="btn btn-secondary btn-sm" href="{{ route('business.sunu.payins.index', array_merge(request()->query(), ['export' => 'csv'])) }}">CSV</a>
            <a class="btn btn-secondary btn-sm" href="{{ route('business.sunu.payins.index', array_merge(request()->query(), ['export' => 'excel'])) }}">Excel</a>
        </div>
    </div>
    <form method="GET" action="{{ route('business.sunu.payins.index') }}" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
        <select name="status" class="form-select">
            <option value="">{{ __('common.all') }}</option>
            @foreach(['declared', 'confirmed', 'rejected'] as $st)
                <option value="{{ $st }}" @selected(request('status') === $st)>{{ __('common.sunu_status_'.$st) }}</option>
            @endforeach
        </select>
        <select name="agent_id" class="form-select">
            <option value="">{{ __('common.agent') }} — {{ __('common.all') }}</option>
            @foreach($agents as $a)
                <option value="{{ $a->id }}" @selected(request('agent_id') == $a->id)>{{ $a->full_name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" class="form-input" value="{{ request('from') }}">
        <input type="date" name="to" class="form-input" value="{{ request('to') }}">
        <button class="btn btn-sm" type="submit">{{ __('common.filter') }}</button>
    </form>

    <div style="overflow-x:auto;">
    <table class="table">
        <thead>
            <tr>
                <th>{{ __('common.sunu_payin_reference') }}</th>
                <th>{{ __('common.date') }}</th>
                <th>{{ __('common.agent') }}</th>
                <th>{{ __('common.sunu_declared_amount') }}</th>
                <th>{{ __('common.sunu_method') }}</th>
                <th>{{ __('common.sunu_payin_bank_reference') }}</th>
                <th>{{ __('common.status') }}</th>
                <th>{{ __('common.actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payIns as $p)
                @php
                    $statusKey = 'common.sunu_status_'.$p->status;
                    $statusLabel = __($statusKey);
                    if ($statusLabel === $statusKey) {
                        $statusLabel = $p->status;
                    }
                @endphp
                <tr>
                    <td><strong>{{ $p->reference }}</strong></td>
                    <td>
                        {{ optional($p->declared_at)->format('Y-m-d H:i') }}
                        <div class="muted" style="font-size:11px;">{{ __('common.sunu_payin_source_'.($p->source ?: 'tpe')) }}@if($p->deposited_at) · {{ __('common.sunu_payin_deposited_at') }} {{ $p->deposited_at->format('Y-m-d') }}@endif</div>
                    </td>
                    <td>{{ $p->agent?->full_name }}</td>
                    <td>
                        {{ $fmt($p->declared_amount) }} XOF
                        @if($p->confirmed_amount !== null && (float) $p->confirmed_amount !== (float) $p->declared_amount)
                            <div style="font-size:11px;">{{ __('common.sunu_confirmed_amount') }} {{ $fmt($p->confirmed_amount) }}</div>
                        @endif
                    </td>
                    <td>{{ $methodLabel($p->method) }}</td>
                    <td>
                        {{ $p->bank_reference ?: '—' }}
                        @if($p->bank_name)<div class="muted" style="font-size:11px;">{{ $p->bank_name }}{{ $p->bank_account ? ' · '.$p->bank_account : '' }}</div>@endif
                        @if($p->deposit_slip_path)<div><a href="{{ route('business.sunu.payins.slip', $p->id) }}" target="_blank" style="font-size:12px;">{{ __('common.sunu_payin_slip') }}</a></div>@endif
                    </td>
                    <td>
                        {{ $statusLabel }}
                        @if($p->confirmer)<div class="muted" style="font-size:11px;">{{ $p->confirmer->name }} · {{ optional($p->confirmed_at)->format('Y-m-d H:i') }}</div>@endif
                        @if($p->notes)<div class="muted" style="font-size:11px;white-space:pre-line;">{{ $p->notes }}</div>@endif
                    </td>
                    <td>
                        @if($p->status === 'declared' && $canValidate)
                            <details>
                                <summary class="btn btn-sm" style="list-style:none;cursor:pointer;">{{ __('common.sunu_confirm') }}</summary>
                                <form method="POST" action="{{ route('business.sunu.payins.confirm', $p->id) }}" enctype="multipart/form-data" style="display:grid;gap:6px;margin-top:6px;min-width:220px;">
                                    @csrf
                                    <input type="number" name="confirmed_amount" min="0" step="1" class="form-input" value="{{ (int) $p->declared_amount }}" title="{{ __('common.sunu_confirmed_amount') }}">
                                    <input type="text" name="bank_reference" maxlength="128" class="form-input" value="{{ $p->bank_reference }}" placeholder="{{ __('common.sunu_payin_bank_reference') }}" @if($p->method === 'bank_deposit' && ! $p->bank_reference) required @endif>
                                    <input type="text" name="bank_name" maxlength="128" class="form-input" value="{{ $p->bank_name }}" placeholder="{{ __('common.sunu_payin_bank_name') }}">
                                    <input type="date" name="deposited_at" class="form-input" max="{{ now()->toDateString() }}" value="{{ optional($p->deposited_at ?? $p->declared_at)->toDateString() }}">
                                    <input type="file" name="deposit_slip" accept="image/*,application/pdf" class="form-input">
                                    <input type="text" name="notes" maxlength="1000" class="form-input" placeholder="{{ __('common.notes') }}">
                                    <button class="btn btn-sm" type="submit">{{ __('common.sunu_confirm') }}</button>
                                </form>
                            </details>
                            <details style="margin-top:6px;">
                                <summary class="btn btn-secondary btn-sm" style="list-style:none;cursor:pointer;">{{ __('common.sunu_reject') }}</summary>
                                <form method="POST" action="{{ route('business.sunu.payins.reject', $p->id) }}" style="display:grid;gap:6px;margin-top:6px;">
                                    @csrf
                                    <input type="text" name="notes" required maxlength="1000" class="form-input" placeholder="{{ __('common.sunu_payin_reject_reason') }}">
                                    <button class="btn btn-secondary btn-sm" type="submit">{{ __('common.sunu_reject') }}</button>
                                </form>
                            </details>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8">{{ __('common.sunu_no_payins') }}</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    {{ $payIns->links() }}
</div>

<script>
    function sunuPrefillDeposit(agentId, held) {
        var agent = document.getElementById('deposit-agent');
        var amount = document.getElementById('deposit-amount');
        if (agent) { agent.value = agentId; }
        if (amount) { amount.value = held; }
    }
    (function () {
        var agent = document.getElementById('deposit-agent');
        var amount = document.getElementById('deposit-amount');
        if (!agent || !amount) { return; }
        agent.addEventListener('change', function () {
            var opt = agent.options[agent.selectedIndex];
            if (opt && opt.dataset.held && !amount.value) { amount.value = opt.dataset.held; }
        });
    })();
</script>
@endsection
