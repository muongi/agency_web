@extends('layouts.app')

@section('title', __('common.cards'))
@section('page-title', __('common.cards'))

@section('content')
@php
    $user = auth()->user();
    $businessUnit = $user->currentBusinessUnit;
    $canViewCards = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-cards', $businessUnit->id));
    $canCreateCard = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('create-card', $businessUnit->id));
@endphp

@if(!$canViewCards)
    <div class="card">
        <div style="text-align: center; padding: 40px; color: var(--error-text);">
            <p>{{ __('common.unauthorized_access') }}</p>
            <a href="{{ route('business.dashboard') }}" class="btn">{{ __('common.back_to_dashboard') }}</a>
        </div>
    </div>
@else
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: var(--accent-color);">{{ __('common.cards') }}</h1>
        <div style="display: flex; gap: 10px; align-items: center;">
            <div style="position: relative;">
                <button onclick="toggleExportDropdown()" class="btn" style="display: flex; align-items: center; gap: 5px;">
                    <span>📥</span>
                    <span>{{ __('common.export') }}</span>
                    <span>▼</span>
                </button>
                <div id="exportDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 5px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 150px;">
                    <a href="{{ route('cards.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_csv') }}
                    </a>
                    <a href="{{ route('cards.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_excel') }}
                    </a>
                    <a href="{{ route('cards.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                        {{ __('common.export_as_pdf') }}
                    </a>
                </div>
            </div>
            @if($canCreateCard)
                <a href="{{ route('cards.create') }}" class="btn">+ {{ __('common.add_card') }}</a>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('cards.index') }}" style="margin-bottom: 20px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.card_number') }}</label>
                <input type="text" name="card_number" class="form-control" value="{{ request('card_number') }}" placeholder="{{ __('common.search_card_number') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.status') }}</label>
                <select name="status" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('common.active') }}</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>{{ __('common.inactive') }}</option>
                    <option value="blocked" {{ request('status') == 'blocked' ? 'selected' : '' }}>{{ __('common.blocked') }}</option>
                    <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>{{ __('common.expired') }}</option>
                    <option value="replaced" {{ request('status') == 'replaced' ? 'selected' : '' }}>{{ __('common.replaced') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.account') }}</label>
                <select name="account_id" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ request('account_id') == $account->id ? 'selected' : '' }}>
                            {{ $account->account_number }} - {{ $account->accountable->full_name ?? 'N/A' }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.client') }}</label>
                <select name="client_id" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach($accounts->unique('accountable_id') as $account)
                        @if($account->accountable_type == 'App\Models\Client')
                            <option value="{{ $account->accountable_id }}" {{ request('client_id') == $account->accountable_id ? 'selected' : '' }}>
                                {{ $account->accountable->full_name ?? 'N/A' }}
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>
        </div>
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn">{{ __('common.filter') }}</button>
            <a href="{{ route('cards.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
        </div>
    </form>

    @if($cards->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.card_number') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.account') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.client') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.status') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.expiration_date') }}</th>
                        <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.daily_transaction_limit') }}</th>
                        <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.daily_withdrawal_limit') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cards as $card)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px;">
                                <strong>{{ $card->card_number }}</strong>
                                @if($card->isReplaced() && $card->replacedByCard)
                                    <br><small style="color: var(--text-muted);">{{ __('common.replaced_by') }}: {{ $card->replacedByCard->card_number }}</small>
                                @endif
                            </td>
                            <td style="padding: 12px;">{{ $card->account->account_number }}</td>
                            <td style="padding: 12px;">
                                @if($card->account->accountable_type == 'App\Models\Client')
                                    {{ $card->account->accountable->full_name ?? 'N/A' }}
                                @else
                                    N/A
                                @endif
                            </td>
                            <td style="padding: 12px;">
                                @php
                                    $statusColors = [
                                        'active' => 'var(--success-bg)',
                                        'inactive' => 'var(--bg-tertiary)',
                                        'blocked' => 'var(--error-bg)',
                                        'expired' => 'var(--warning-bg)',
                                        'replaced' => 'var(--text-muted)',
                                    ];
                                    $statusColor = $statusColors[$card->status] ?? 'var(--bg-tertiary)';
                                @endphp
                                <span style="padding: 4px 12px; border-radius: 4px; background: {{ $statusColor }}; color: var(--text-primary); font-size: 12px;">
                                    {{ __('common.' . $card->status) }}
                                </span>
                            </td>
                            <td style="padding: 12px;">{{ $card->expiration_date->format('Y-m-d') }}</td>
                            <td style="padding: 12px; text-align: right;">{{ $card->daily_transaction_limit ? number_format($card->daily_transaction_limit, 2) : '-' }}</td>
                            <td style="padding: 12px; text-align: right;">{{ $card->daily_withdrawal_limit ? number_format($card->daily_withdrawal_limit, 2) : '-' }}</td>
                            <td style="padding: 12px;">
                                @php
                                    $canView = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-card', $businessUnit->id));
                                    $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-card', $businessUnit->id));
                                @endphp
                                <div style="display: flex; gap: 5px;">
                                    @if($canView)
                                        <a href="{{ route('cards.show', $card->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.view') }}</a>
                                    @endif
                                    @if($canEdit)
                                        <a href="{{ route('cards.edit', $card->id) }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            {{ $cards->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <p>{{ __('common.no_cards_found') }}</p>
            @if($canCreateCard)
                <a href="{{ route('cards.create') }}" class="btn" style="margin-top: 15px;">{{ __('common.add_first_card') }}</a>
            @endif
        </div>
    @endif
</div>
@endif

<script>
function toggleExportDropdown() {
    const dropdown = document.getElementById('exportDropdown');
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
}

document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('exportDropdown');
    const button = event.target.closest('button[onclick="toggleExportDropdown()"]');
    if (!button && !dropdown.contains(event.target)) {
        dropdown.style.display = 'none';
    }
});
</script>
@endsection

