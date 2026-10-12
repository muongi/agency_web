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
        <h1 class="text-accent">{{ __('common.cards') }}</h1>
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

    @if($canCreateCard)
        <details style="margin-bottom: 20px; padding: 16px 20px; background: var(--bg-tertiary); border-radius: 8px;" {{ session('import_errors') ? 'open' : '' }}>
            <summary style="cursor: pointer; font-weight: 600; color: var(--accent-color);">{{ __('common.sunu_card_import_title') }}</summary>
            <p class="muted" style="margin: 10px 0;">{{ __('common.sunu_card_import_help') }}</p>
            <form method="POST" action="{{ route('cards.import-uids') }}" enctype="multipart/form-data">
                @csrf
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px;">
                    <div class="form-group">
                        <label class="form-label" for="import_file">{{ __('common.sunu_card_import_file') }} <span class="text-error">*</span></label>
                        <input type="file" id="import_file" name="file" class="form-control" accept=".csv,.txt,.xlsx,.xls" required>
                        @error('file')<div class="field-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="uid_format">{{ __('common.sunu_card_import_format') }}</label>
                        <select id="uid_format" name="uid_format" class="form-select">
                            <option value="hex">{{ __('common.sunu_card_import_format_hex') }}</option>
                            <option value="hex_reversed">{{ __('common.sunu_card_import_format_hex_reversed') }}</option>
                            <option value="decimal">{{ __('common.sunu_card_import_format_decimal') }}</option>
                        </select>
                        <small class="muted">{{ __('common.sunu_card_import_format_help') }}</small>
                    </div>
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn">{{ __('common.sunu_card_import_submit') }}</button>
                    <a href="{{ route('cards.import-uids.sample') }}" class="btn btn-secondary">{{ __('common.sunu_card_import_sample') }}</a>
                </div>
            </form>
            @if(session('import_errors'))
                <ul style="margin: 12px 0 0; padding-left: 18px; color: var(--error-text); font-size: 13px;">
                    @foreach(session('import_errors') as $importError)
                        <li>{{ $importError }}</li>
                    @endforeach
                </ul>
            @endif
        </details>
    @endif

    <!-- Filters -->
    <form method="GET" action="{{ route('cards.index') }}" style="margin-bottom: 20px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.card_number') }}</label>
                <input type="text" name="card_number" class="form-control" value="{{ request('card_number') }}" placeholder="{{ __('common.sunu_card_search_number_uid') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.status') }}</label>
                <select name="status" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="unassigned" {{ request('status') == 'unassigned' ? 'selected' : '' }}>{{ __('common.sunu_card_stock_status') }}</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('common.active') }}</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>{{ __('common.inactive') }}</option>
                    <option value="blocked" {{ request('status') == 'blocked' ? 'selected' : '' }}>{{ __('common.blocked') }}</option>
                    <option value="expired" {{ request('status') == 'expired' ? 'selected' : '' }}>{{ __('common.expired') }}</option>
                    <option value="replaced" {{ request('status') == 'replaced' ? 'selected' : '' }}>{{ __('common.replaced') }}</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.sunu_card_client') }}</label>
                <select name="client_id" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    @foreach($clients as $client)
                        <option value="{{ $client['id'] }}" {{ (string) request('client_id') === (string) $client['id'] ? 'selected' : '' }}>
                            {{ $client['label'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="btn-row">
            <button type="submit" class="btn">{{ __('common.filter') }}</button>
            <a href="{{ route('cards.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
        </div>
    </form>

    @if($cards->count() > 0)
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th>{{ __('common.card_number') }}</th>
                        <th>{{ __('common.sunu_card_uid') }}</th>
                        <th>{{ __('common.sunu_card_client') }}</th>
                        <th>{{ __('common.sunu_card_subscriptions') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('common.sunu_card_written_on_tpe') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cards as $card)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>
                                <strong>{{ $card->card_number }}</strong>
                                @if($card->isReplaced() && $card->replacedByCard)
                                    <br><small class="muted">{{ __('common.replaced_by') }}: {{ $card->replacedByCard->card_number }}</small>
                                @endif
                            </td>
                            @php
                                $sunuClient = $card->sunuClient();
                                $numbers = $card->sunuPolicies->pluck('policy_number')
                                    ->merge($card->sunuProposals->pluck('proposal_number'));
                            @endphp
                            <td><code>{{ $card->card_serial ?? '-' }}</code></td>
                            <td>
                                @if($sunuClient)
                                    {{ trim($sunuClient->last_name . ' ' . $sunuClient->first_name) }}
                                    @if($sunuClient->phone_number)<br><small class="muted">{{ $sunuClient->phone_number }}</small>@endif
                                @else
                                    <span class="muted">{{ __('common.sunu_card_not_linked') }}</span>
                                @endif
                            </td>
                            <td>{{ $numbers->isNotEmpty() ? $numbers->implode(', ') : '-' }}</td>
                            <td>
                                @php
                                    $statusColors = [
                                        'unassigned' => 'var(--warning-bg)',
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
                            <td>{{ $card->written_at ? $card->written_at->format('Y-m-d H:i') : '-' }}</td>
                            <td>
                                @php
                                    $canView = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-card', $businessUnit->id));
                                    $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-card', $businessUnit->id));
                                @endphp
                                <div style="display: flex; gap: 5px;">
                                    @if($canView)
                                        <a href="{{ route('cards.show', $card->id) }}" class="btn btn-sm">{{ __('common.view') }}</a>
                                    @endif
                                    @if($canEdit)
                                        <a href="{{ route('cards.edit', $card->id) }}" class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                    @endif
                                    @if($canCreateCard && $card->status === 'unassigned' && $card->card_serial && !$sunuClient)
                                        <a href="{{ route('cards.create', ['uid' => $card->card_serial]) }}" class="btn btn-sm">{{ __('common.sunu_card_assign') }}</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-20">
            {{ $cards->links() }}
        </div>
    @else
        <div class="muted-center">
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

