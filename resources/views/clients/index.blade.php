@extends('layouts.app')

@section('title', __('common.clients'))
@section('page-title', __('common.clients'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: var(--accent-color);">{{ __('common.clients') }}</h1>
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
                <button type="button" class="btn btn-secondary" onclick="submitKatoBulkSync()">
                    {{ __('common.kato_sync_selected_members') }}
                </button>
            @endif
        </div>
    </div>

    <!-- Search Form -->
    <form method="GET" action="{{ route('clients.index') }}" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 10px;">
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
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        @if(!empty($canSyncKato))
                            <th style="padding: 12px; text-align: left; color: var(--accent-color); width: 40px;">
                                <input type="checkbox" id="select-all-clients" title="{{ __('common.select_all') }}">
                            </th>
                        @endif
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.photo') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.name') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.email') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.phone') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.gender') }}</th>
                        @if(!empty($isKato))
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.kato_member_no') }}</th>
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.kato_account_numbers') }}</th>
                        @endif
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.year_of_birth') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($clients as $client)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            @if(!empty($canSyncKato))
                                <td style="padding: 12px;">
                                    @if(!$client->kato_member_no)
                                        <input type="checkbox" name="client_ids[]" value="{{ $client->id }}" class="client-sync-checkbox">
                                    @endif
                                </td>
                            @endif
                            <td style="padding: 12px;">
                                @if($client->photo)
                                    <img src="{{ asset('storage/' . $client->photo) }}" alt="{{ $client->full_name }}" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                @else
                                    <div style="width: 50px; height: 50px; border-radius: 50%; background: var(--bg-tertiary); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                                        {{ strtoupper(substr($client->first_name, 0, 1) . substr($client->last_name, 0, 1)) }}
                                    </div>
                                @endif
                            </td>
                            <td style="padding: 12px;">{{ $client->full_name }}</td>
                            <td style="padding: 12px;">{{ $client->email ?? '-' }}</td>
                            <td style="padding: 12px;">{{ $client->phone_number ?? '-' }}</td>
                            <td style="padding: 12px;">{{ $client->gender ?? '-' }}</td>
                            @if(!empty($isKato))
                                <td style="padding: 12px;">
                                    @if($client->kato_member_no)
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #2d5a2d; color: #90ee90;" title="{{ __('common.kato_registered') }}">
                                            {{ $client->kato_member_no }}
                                        </span>
                                    @elseif($client->kato_registration_error)
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #5a2d2d; color: #ee9090;" title="{{ $client->kato_registration_error }}">
                                            {{ __('common.failed') }}
                                        </span>
                                    @else
                                        <span style="color: var(--text-muted);">{{ __('common.not_synced_with_kato') }}</span>
                                    @endif
                                </td>
                                <td style="padding: 12px;">
                                    @php
                                        $katoAccountNumbers = $client->accounts->pluck('account_number')->filter()->values();
                                    @endphp
                                    @if($katoAccountNumbers->isNotEmpty())
                                        @if($katoAccountNumbers->count() === 1)
                                            <code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px; font-size: 12px;">{{ $katoAccountNumbers->first() }}</code>
                                        @else
                                            <span title="{{ $katoAccountNumbers->implode(', ') }}">
                                                <code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px; font-size: 12px;">{{ $katoAccountNumbers->first() }}</code>
                                                <span style="color: var(--text-muted); font-size: 12px;">+{{ $katoAccountNumbers->count() - 1 }}</span>
                                            </span>
                                        @endif
                                    @else
                                        <span style="color: var(--text-muted);">-</span>
                                    @endif
                                </td>
                            @endif
                            <td style="padding: 12px;">{{ $client->year_of_birth ?? '-' }}</td>
                            <td style="padding: 12px;">
                                @php
                                    $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-client', $businessUnit->id));
                                    $canDelete = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('delete-client', $businessUnit->id));
                                @endphp
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('clients.show', $client->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.view') }}</a>
                                    @if($canEdit)
                                        <a href="{{ route('clients.edit', $client->id) }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                                    @endif
                                    @if($canDelete)
                                        <form action="{{ route('clients.destroy', $client->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">{{ __('common.delete') }}</button>
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
                    const container = document.getElementById('kato-bulk-sync-inputs');
                    const form = document.getElementById('kato-bulk-sync-form');
                    if (!container || !form) {
                        return;
                    }
                    container.innerHTML = '';
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

        <div style="margin-top: 20px;">
            {{ $clients->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <p style="margin-bottom: 20px;">{{ __('common.no_clients') }}</p>
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

