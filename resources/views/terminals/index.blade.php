@extends('layouts.app')

@section('title', __('common.terminals'))
@section('page-title', __('common.terminals'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: var(--accent-color);">{{ __('common.terminals') }}</h1>
        <div style="display: flex; gap: 10px; align-items: center;">
            <div style="position: relative;">
                <button onclick="toggleExportDropdown()" class="btn" style="display: flex; align-items: center; gap: 5px;">
                    <span>📥</span>
                    <span>{{ __('common.export') }}</span>
                    <span>▼</span>
                </button>
                <div id="exportDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 5px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 150px;">
                    <a href="{{ route('terminals.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_csv') }}
                    </a>
                    <a href="{{ route('terminals.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                        {{ __('common.export_as_excel') }}
                    </a>
                    <a href="{{ route('terminals.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                        {{ __('common.export_as_pdf') }}
                    </a>
                </div>
            </div>
            @php
                $user = auth()->user();
                $businessUnit = $user->currentBusinessUnit;
                $canCreate = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('create-terminal', $businessUnit->id));
            @endphp
            @if($canCreate)
                <a href="{{ route('terminals.create') }}" class="btn">+ {{ __('common.add_terminal') }}</a>
            @endif
        </div>
    </div>

    <!-- Search Form -->
    <form method="GET" action="{{ route('terminals.index') }}" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 10px;">
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                placeholder="{{ __('common.search_terminals') }}..." 
                value="{{ request('search') }}"
                style="flex: 1;"
            >
            <button type="submit" class="btn">{{ __('common.search') }}</button>
            @if(request('search'))
                <a href="{{ route('terminals.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
            @endif
        </div>
    </form>

    @if($terminals->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">#</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.serial_number') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.simcard_number') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.device_alias') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.model_make') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($terminals as $index => $terminal)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px;">{{ ($terminals->currentPage() - 1) * $terminals->perPage() + $index + 1 }}</td>
                            <td style="padding: 12px;">{{ $terminal->serial_number }}</td>
                            <td style="padding: 12px;">{{ $terminal->simcard_number }}</td>
                            <td style="padding: 12px;">{{ $terminal->device_alias }}</td>
                            <td style="padding: 12px;">{{ $terminal->model_make }}</td>
                            <td style="padding: 12px;">
                                @php
                                    $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-terminal', $businessUnit->id));
                                    $canDelete = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('delete-terminal', $businessUnit->id));
                                @endphp
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('terminals.show', $terminal->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.show') }}</a>
                                    @if($canEdit)
                                        <a href="{{ route('terminals.edit', $terminal->id) }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                                    @endif
                                    @if($canDelete)
                                        <form action="{{ route('terminals.destroy', $terminal->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
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

        <div style="margin-top: 20px;">
            {{ $terminals->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <p style="margin-bottom: 20px;">{{ __('common.no_terminals') }}</p>
            @if($canCreate)
                <a href="{{ route('terminals.create') }}" class="btn">{{ __('common.add_terminal') }}</a>
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

