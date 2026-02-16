@extends('layouts.app')

@section('title', __('common.accounts'))
@section('page-title', __('common.accounts'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: var(--accent-color);">{{ __('common.accounts') }}</h1>
        <div style="display: flex; gap: 10px; align-items: center;">
            <div style="position: relative;">
                <button onclick="toggleExportDropdown()" class="btn" style="display: flex; align-items: center; gap: 5px;">
                    <span>📥</span>
                    <span>{{ __('common.export') }}</span>
                    <span>▼</span>
                </button>
                <div id="exportDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 5px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 150px;">
                    <a href="{{ route('accounts.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_csv') }}
                    </a>
                    <a href="{{ route('accounts.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_excel') }}
                    </a>
                    <a href="{{ route('accounts.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                        {{ __('common.export_as_pdf') }}
                    </a>
                </div>
            </div>
            @php
                $user = auth()->user();
                $businessUnit = $user->currentBusinessUnit;
                $canCreate = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('create-account', $businessUnit->id));
            @endphp
            @if($canCreate)
                <a href="{{ route('accounts.create') }}" class="btn">+ {{ __('common.add_account') }}</a>
            @endif
        </div>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('accounts.index') }}" style="margin-bottom: 20px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <div>
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="{{ __('common.search_accounts') }}..." 
                    value="{{ request('search') }}"
                >
            </div>
            <div>
                <select name="account_type" class="form-select">
                    <option value="">{{ __('common.all_account_types') }}</option>
                    <option value="general" {{ request('account_type') == 'general' ? 'selected' : '' }}>{{ __('common.general') }}</option>
                    <option value="float" {{ request('account_type') == 'float' ? 'selected' : '' }}>{{ __('common.float') }}</option>
                    <option value="commission" {{ request('account_type') == 'commission' ? 'selected' : '' }}>{{ __('common.commission') }}</option>
                </select>
            </div>
            <div>
                <select name="status" class="form-select">
                    <option value="">{{ __('common.all_statuses') }}</option>
                    <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ __('common.active') }}</option>
                    <option value="inactive" {{ request('status') == 'inactive' ? 'selected' : '' }}>{{ __('common.inactive') }}</option>
                    <option value="suspended" {{ request('status') == 'suspended' ? 'selected' : '' }}>{{ __('common.suspended') }}</option>
                    <option value="closed" {{ request('status') == 'closed' ? 'selected' : '' }}>{{ __('common.closed') }}</option>
                </select>
            </div>
            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn">{{ __('common.filter') }}</button>
                @if(request('search') || request('account_type') || request('status'))
                    <a href="{{ route('accounts.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
                @endif
            </div>
        </div>
    </form>

    @if($accounts->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.account_number') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.owner') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.account_type') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.balance') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.currency') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.status') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $account)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px;"><code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $account->account_number }}</code></td>
                            <td style="padding: 12px;">
                                @if($account->accountable_type === 'App\Models\Client')
                                    <span>{{ $account->accountable->full_name ?? 'N/A' }}</span>
                                    <br><small style="color: var(--text-muted);">({{ __('common.client') }})</small>
                                @else
                                    <span>{{ $account->accountable->full_name ?? 'N/A' }}</span>
                                    <br><small style="color: var(--text-muted);">({{ __('common.agent') }})</small>
                                @endif
                            </td>
                            <td style="padding: 12px; text-transform: capitalize;">{{ $account->account_type }}</td>
                            <td style="padding: 12px; font-weight: bold;">{{ number_format($account->balance, 2) }}</td>
                            <td style="padding: 12px;">{{ $account->currency }}</td>
                            <td style="padding: 12px;">
                                <span style="text-transform: capitalize; padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                    @if($account->status === 'active') background: var(--success-bg); color: var(--success-text);
                                    @elseif($account->status === 'inactive') background: var(--bg-tertiary); color: var(--text-muted);
                                    @elseif($account->status === 'suspended') background: #ff9800; color: white;
                                    @else background: var(--error-bg); color: var(--error-text);
                                    @endif">
                                    {{ $account->status }}
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                @php
                                    $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-account', $businessUnit->id));
                                    $canDelete = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('delete-account', $businessUnit->id));
                                @endphp
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('accounts.show', $account->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.view') }}</a>
                                    @if($canEdit)
                                        <a href="{{ route('accounts.edit', $account->id) }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                                    @endif
                                    @if($canDelete && !$account->isRequired())
                                        <form action="{{ route('accounts.destroy', $account->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">{{ __('common.delete') }}</button>
                                        </form>
                                    @elseif($account->isRequired())
                                        <span style="color: var(--text-muted); font-size: 11px;" title="{{ __('common.required_account_cannot_delete') }}">{{ __('common.required_account') }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            {{ $accounts->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <p style="margin-bottom: 20px;">{{ __('common.no_accounts') }}</p>
            @if($canCreate)
                <a href="{{ route('accounts.create') }}" class="btn">{{ __('common.add_account') }}</a>
            @endif
        </div>
    @endif
</div>

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

