@extends('layouts.app')

@section('title', __('common.agents'))
@section('page-title', __('common.agents'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 class="text-accent">{{ __('common.agents') }}</h1>
        <div style="display: flex; gap: 10px; align-items: center;">
            <div style="position: relative;">
                <button onclick="toggleExportDropdown()" class="btn" style="display: flex; align-items: center; gap: 5px;">
                    <span>📥</span>
                    <span>{{ __('common.export') }}</span>
                    <span>▼</span>
                </button>
                <div id="exportDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 5px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 150px;">
                    <a href="{{ route('agents.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_csv') }}
                    </a>
                    <a href="{{ route('agents.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_excel') }}
                    </a>
                    <a href="{{ route('agents.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                        {{ __('common.export_as_pdf') }}
                    </a>
                </div>
            </div>
            @php
                $user = auth()->user();
                $businessUnit = $user->currentBusinessUnit;
                $canCreate = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('create-agent', $businessUnit->id));
            @endphp
            @if($canCreate)
                <a href="{{ route('agents.create') }}" class="btn">+ {{ __('common.add_agent') }}</a>
            @endif
        </div>
    </div>

    <!-- Search Form -->
    <form method="GET" action="{{ route('agents.index') }}" class="mb-20">
        <div class="btn-row">
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                placeholder="{{ __('common.search_agents') }}..." 
                value="{{ request('search') }}"
                style="flex: 1;"
            >
            <button type="submit" class="btn">{{ __('common.search') }}</button>
            @if(request('search'))
                <a href="{{ route('agents.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
            @endif
        </div>
    </form>

    @if($agents->count() > 0)
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th>#</th>
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('common.username') }}</th>
                        <th>{{ __('common.email') }}</th>
                        <th>{{ __('common.phone') }}</th>
                        <th>{{ __('common.kato_agent_id') }}</th>
                        <th>{{ __('common.terminals') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($agents as $index => $agent)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>{{ ($agents->currentPage() - 1) * $agents->perPage() + $index + 1 }}</td>
                            <td>{{ $agent->full_name }}</td>
                            <td>{{ $agent->username }}</td>
                            <td>{{ $agent->email }}</td>
                            <td>{{ $agent->phone_number ?? '-' }}</td>
                            <td>
                                @if($agent->kato_agent_id)
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--success-bg); color: var(--success-text);" title="{{ __('common.kato_registered') }}">
                                        {{ $agent->kato_agent_id }}
                                    </span>
                                @elseif($agent->kato_registration_error)
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--error-bg); color: var(--error-text);" title="{{ $agent->kato_registration_error }}">
                                        {{ __('common.failed') }}
                                    </span>
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span style="color: var(--accent-color); font-weight: bold;">{{ $agent->terminals_count }}</span>
                            </td>
                            <td>
                                @php
                                    $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-agent', $businessUnit->id));
                                    $canDelete = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('delete-agent', $businessUnit->id));
                                @endphp
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('agents.show', $agent->id) }}" class="btn btn-sm">{{ __('common.show') }}</a>
                                    @if($canEdit)
                                        <a href="{{ route('agents.edit', $agent->id) }}" class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                    @endif
                                    @if($canDelete)
                                        <form action="{{ route('agents.destroy', $agent->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
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

        <div class="mt-20">
            {{ $agents->links() }}
        </div>
    @else
        <div class="muted-center">
            <p class="mb-20">{{ __('common.no_agents') }}</p>
            @if($canCreate)
                <a href="{{ route('agents.create') }}" class="btn">{{ __('common.add_agent') }}</a>
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

