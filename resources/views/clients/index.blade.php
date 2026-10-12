@extends('layouts.app')

@section('title', __('common.clients'))
@section('page-title', __('common.clients'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 class="text-accent">{{ __('common.clients') }}</h1>
        <div style="display: flex; gap: 10px; align-items: center;">
            <div style="position: relative;">
                <button onclick="toggleExportDropdown()" class="btn" style="display: flex; align-items: center; gap: 5px;">
                    <span>📥</span>
                    <span>{{ __('common.export') }}</span>
                    <span>▼</span>
                </button>
                <div id="exportDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 5px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 150px;">
                    <a href="{{ route('clients.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_csv') }}
                    </a>
                    <a href="{{ route('clients.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_excel') }}
                    </a>
                    <a href="{{ route('clients.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                        {{ __('common.export_as_pdf') }}
                    </a>
                </div>
            </div>
            @php
                $user = auth()->user();
                $businessUnit = $user->currentBusinessUnit;
                $canCreate = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('create-client', $businessUnit->id));
            @endphp
            @if($canCreate)
                <a href="{{ route('clients.create') }}" class="btn">+ {{ __('common.add_client') }}</a>
            @endif
            @php
                $isKato = $businessUnit && $businessUnit->isKatoEnabled();
                $canSyncKato = $isKato && $businessUnit && ($user->isSuperAdmin() || $user->hasPermission('edit-client', $businessUnit->id));
            @endphp
            @if($canSyncKato && $clients->count() > 0)
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    @if(($katoAgents ?? collect())->isNotEmpty())
                        <select id="kato-bulk-sync-agent" class="form-select" style="min-width: 220px;" required>
                            <option value="">— {{ __('common.agent') }} —</option>
                            @foreach($katoAgents as $katoAgent)
                                <option value="{{ $katoAgent->id }}">
                                    {{ $katoAgent->full_name }} ({{ $katoAgent->katoFloatAccountNumber() }})
                                </option>
                            @endforeach
                        </select>
                    @endif
                    <button type="button" class="btn btn-secondary" onclick="submitKatoBulkSync()" @if(($katoAgents ?? collect())->isEmpty()) disabled @endif>
                        {{ __('common.kato_sync_selected_members') }}
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- Search Form -->
    <form method="GET" action="{{ route('clients.index') }}" class="mb-20">
        <div class="btn-row">
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                placeholder="{{ __('common.search_clients') }}..." 
                value="{{ request('search') }}"
                style="flex: 1;"
            >
            <button type="submit" class="btn">{{ __('common.search') }}</button>
            @if(request('search'))
                <a href="{{ route('clients.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
            @endif
        </div>
    </form>

    @if($clients->count() > 0)
        @if(!empty($canSyncKato))
            <form id="kato-bulk-sync-form" method="POST" action="{{ route('clients.sync-kato-bulk') }}" style="display: none;">
                @csrf
                <div id="kato-bulk-sync-inputs"></div>
            </form>
        @endif
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        @if(!empty($canSyncKato))
                            <th style="padding: 12px; text-align: left; color: var(--accent-color); width: 40px;">
                                <input type="checkbox" id="select-all-clients" title="{{ __('common.select_all') }}">
                            </th>
                        @endif
                        <th>{{ __('common.photo') }}</th>
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('common.email') }}</th>
                        <th>{{ __('common.phone') }}</th>
                        <th>{{ __('common.gender') }}</th>
                        @if(!empty($isKato))
                            <th>{{ __('common.kato_member_no') }}</th>
                            <th>{{ __('common.kato_account_numbers') }}</th>
                        @endif
                        <th>{{ __('common.year_of_birth') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $client)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            @if(!empty($canSyncKato))
                                <td>
                                    @if(!$client->kato_member_no)
                                        <input type="checkbox" name="client_ids[]" value="{{ $client->id }}" class="client-sync-checkbox">
                                    @endif
                                </td>
                            @endif
                            <td>
                                @if($client->photo)
                                    <img src="{{ asset('storage/' . $client->photo) }}" alt="{{ $client->full_name }}" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                @else
                                    <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                                        {{ strtoupper(substr($client->first_name, 0, 1) . substr($client->last_name, 0, 1)) }}
                                    </div>
                                @endif
                            </td>
                            <td>{{ $client->full_name }}</td>
                            <td>{{ $client->email ?? '-' }}</td>
                            <td>{{ $client->phone_number ?? '-' }}</td>
                            <td>{{ $client->gender ?? '-' }}</td>
                            @if(!empty($isKato))
                                <td>
                                    @if($client->kato_member_no)
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--success-bg); color: var(--success-text);" title="{{ __('common.kato_registered') }}">
                                            {{ $client->kato_member_no }}
                                        </span>
                                    @elseif($client->kato_registration_error)
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--error-bg); color: var(--error-text);" title="{{ $client->kato_registration_error }}">
                                            {{ __('common.failed') }}
                                        </span>
                                    @else
                                        <span class="muted">{{ __('common.not_synced_with_kato') }}</span>
                                    @endif
                                </td>
                                <td>
                                    @php
                                        $katoAccountNumbers = $client->accounts->pluck('account_number')->filter()->values();
                                    @endphp
                                    @if($katoAccountNumbers->isNotEmpty())
                                        @if($katoAccountNumbers->count() === 1)
                                            <code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px; font-size: 12px;">{{ $katoAccountNumbers->first() }}</code>
                                        @else
                                            <span title="{{ $katoAccountNumbers->implode(', ') }}">
                                                <code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px; font-size: 12px;">{{ $katoAccountNumbers->first() }}</code>
                                                <span class="text-muted-sm">+{{ $katoAccountNumbers->count() - 1 }}</span>
                                            </span>
                                        @endif
                                    @else
                                        <span class="muted">-</span>
                                    @endif
                                </td>
                            @endif
                            <td>{{ $client->year_of_birth ?? '-' }}</td>
                            <td>
                                @php
                                    $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-client', $businessUnit->id));
                                    $canDelete = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('delete-client', $businessUnit->id));
                                @endphp
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('clients.show', $client->id) }}" class="btn btn-sm">{{ __('common.view') }}</a>
                                    @if($canEdit)
                                        <a href="{{ route('clients.edit', $client->id) }}" class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                    @endif
                                    @if($canDelete)
                                        <form action="{{ route('clients.destroy', $client->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">{{ __('common.delete') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if(!empty($canSyncKato))
            <script>
                document.getElementById('select-all-clients')?.addEventListener('change', function() {
                    document.querySelectorAll('.client-sync-checkbox').forEach(cb => cb.checked = this.checked);
                });

                function submitKatoBulkSync() {
                    const checked = document.querySelectorAll('.client-sync-checkbox:checked');
                    if (checked.length === 0) {
                        alert(@json(__('common.select_clients_to_sync')));
                        return;
                    }
                    const agentSelect = document.getElementById('kato-bulk-sync-agent');
                    if (!agentSelect || !agentSelect.value) {
                        alert(@json(__('common.kato_registration_agent_required')));
                        return;
                    }
                    const container = document.getElementById('kato-bulk-sync-inputs');
                    const form = document.getElementById('kato-bulk-sync-form');
                    if (!container || !form) {
                        return;
                    }
                    container.innerHTML = '';
                    const agentInput = document.createElement('input');
                    agentInput.type = 'hidden';
                    agentInput.name = 'agent_id';
                    agentInput.value = agentSelect.value;
                    container.appendChild(agentInput);
                    checked.forEach(function(cb) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'client_ids[]';
                        input.value = cb.value;
                        container.appendChild(input);
                    });
                    form.submit();
                }
            </script>
        @endif

        <div class="mt-20">
            {{ $clients->links() }}
        </div>
    @else
        <div class="muted-center">
            <p class="mb-20">{{ __('common.no_clients') }}</p>
            @if($canCreate)
                <a href="{{ route('clients.create') }}" class="btn">{{ __('common.add_client') }}</a>
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

