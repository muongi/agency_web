@extends('layouts.app')

@section('title', __('common.cnss_all_contributions'))
@section('page-title', __('common.cnss_all_contributions'))

@section('content')
@php
    $user = auth()->user();
    $bu = $user->currentBusinessUnit;
    $canViewCnssMembers = $user->isSuperAdmin() || ($bu && $user->hasPermission('view-cnss-members', $bu->id));
    $currency = config('currency.default', 'CFA');
@endphp

@if(!$canViewCnssMembers)
    <div class="card">
        <div style="text-align: center; padding: 40px; color: var(--error-text);">
            <p>{{ __('common.unauthorized_access') }}</p>
            <a href="{{ route('business.dashboard') }}" class="btn">{{ __('common.back_to_dashboard') }}</a>
        </div>
    </div>
@else
<div class="card" style="margin-bottom: 16px;">
    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;">
        <div>
            <h1 style="color: var(--accent-color); margin: 0 0 8px 0;">{{ __('common.cnss_all_contributions') }}</h1>
            <p style="color: var(--text-muted); margin: 0;">
                <a href="{{ route('business.cnss.members.index') }}">{{ __('common.cnss_members') }}</a>
                ·
                <a href="{{ route('business.cnss.contributions.dashboard') }}">{{ __('common.cnss_dashboard') }}</a>
            </p>
        </div>
        <div style="position: relative;">
            <button type="button" onclick="toggleCnssContributionsExportDropdown()" class="btn" style="display: flex; align-items: center; gap: 5px;">
                <span>📥</span>
                <span>{{ __('common.export') }}</span>
                <span>▼</span>
            </button>
            <div id="cnssContributionsExportDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 5px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 150px;">
                <a href="{{ route('business.cnss.contributions.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                    {{ __('common.export_as_csv') }}
                </a>
                <a href="{{ route('business.cnss.contributions.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                    {{ __('common.export_as_excel') }}
                </a>
                <a href="{{ route('business.cnss.contributions.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                    {{ __('common.export_as_pdf') }}
                </a>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('business.cnss.contributions.index') }}" style="margin-bottom: 20px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_mode') }}</label>
                <select name="mode" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="record" {{ request('mode') === 'record' ? 'selected' : '' }}>{{ __('common.cnss_mode_record') }}</option>
                    <option value="ledger" {{ request('mode') === 'ledger' ? 'selected' : '' }}>{{ __('common.cnss_mode_ledger') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.start_date') }}</label>
                <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.end_date') }}</label>
                <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
            </div>
        </div>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <input type="text" name="search" class="form-control" placeholder="{{ __('common.cnss_contributions_search_placeholder') }}" value="{{ request('search') }}" style="flex: 1; min-width: 200px;">
            <button type="submit" class="btn">{{ __('common.filter') }}</button>
            <a href="{{ route('business.cnss.contributions.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
        </div>
    </form>

    @if($contributions->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.cnss_voluntary_insured_number') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.cnss_affiliation_number') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.cnss_mode') }}</th>
                        <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.cnss_amount') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.cnss_reference_period') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.agent') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.transaction_reference') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.created_at') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contributions as $c)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px;">
                                <code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px;">{{ $c->voluntary_insured_number ?? '—' }}</code>
                            </td>
                            <td style="padding: 12px;">
                                @if($c->affiliation_number)
                                    <code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px;">{{ $c->affiliation_number }}</code>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="padding: 12px;">{{ $c->mode }}</td>
                            <td style="padding: 12px; text-align: right; font-weight: bold;">{{ number_format((float) $c->amount, 2) }} {{ $currency }}</td>
                            <td style="padding: 12px;">{{ $c->reference_period }}</td>
                            <td style="padding: 12px;">{{ $c->agent?->full_name ?? '—' }}</td>
                            <td style="padding: 12px;">{{ $c->reference ?? '—' }}</td>
                            <td style="padding: 12px;">{{ $c->created_at?->format('Y-m-d H:i') }}</td>
                            <td style="padding: 12px;">
                                <div style="display: flex; flex-wrap: wrap; gap: 6px;">
                                    @if($c->enrollment && ($user->isSuperAdmin() || ($bu && $user->hasPermission('view-cnss-member', $bu->id))))
                                        <a href="{{ route('business.cnss.members.show', $c->enrollment->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.cnss_member_detail') }}</a>
                                    @endif
                                    @if($canViewTransactions && $c->transaction_id)
                                        <a href="{{ route('transactions.show', $c->transaction_id) }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">{{ __('common.view_transaction') }}</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div style="margin-top: 20px;">
            {{ $contributions->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <p>{{ __('common.cnss_no_contributions_bu') }}</p>
        </div>
    @endif
</div>
@endif
@endsection

@push('scripts')
<script>
function toggleCnssContributionsExportDropdown() {
    const dropdown = document.getElementById('cnssContributionsExportDropdown');
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
}

document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('cnssContributionsExportDropdown');
    const button = event.target.closest('button[onclick="toggleCnssContributionsExportDropdown()"]');
    if (dropdown && !button && !dropdown.contains(event.target)) {
        dropdown.style.display = 'none';
    }
});
</script>
@endpush
