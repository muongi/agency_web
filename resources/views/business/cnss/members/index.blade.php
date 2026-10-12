@extends('layouts.app')

@section('title', __('common.cnss_members'))
@section('page-title', __('common.cnss_members'))

@section('content')
@php
    $user = auth()->user();
    $businessUnit = $user->currentBusinessUnit;
    $canViewCnssMembers = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-cnss-members', $businessUnit->id));
@endphp

@if(!$canViewCnssMembers)
    <div class="card">
        <div style="text-align: center; padding: 40px; color: var(--error-text);">
            <p>{{ __('common.unauthorized_access') }}</p>
            <a href="{{ route('business.dashboard') }}" class="btn">{{ __('common.back_to_dashboard') }}</a>
        </div>
    </div>
@else
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 class="text-accent">{{ __('common.cnss_members') }}</h1>
        <div style="position: relative;">
            <button type="button" onclick="toggleExportDropdown()" class="btn" style="display: flex; align-items: center; gap: 5px;">
                <span>📥</span>
                <span>{{ __('common.export') }}</span>
                <span>▼</span>
            </button>
            <div id="exportDropdown" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 5px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); z-index: 1000; min-width: 150px;">
                <a href="{{ route('business.cnss.members.export', array_merge(request()->all(), ['format' => 'csv'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                    {{ __('common.export_as_csv') }}
                </a>
                <a href="{{ route('business.cnss.members.export', array_merge(request()->all(), ['format' => 'excel'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary); border-bottom: 1px solid var(--border-color);">
                    {{ __('common.export_as_excel') }}
                </a>
                <a href="{{ route('business.cnss.members.export', array_merge(request()->all(), ['format' => 'pdf'])) }}" class="dropdown-item" style="display: block; padding: 10px 15px; text-decoration: none; color: var(--text-primary);">
                    {{ __('common.export_as_pdf') }}
                </a>
            </div>
        </div>
    </div>

    <form method="GET" action="{{ route('business.cnss.members.index') }}" style="margin-bottom: 20px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.status') }}</label>
                <select name="status" class="form-select">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>{{ __('common.pending') }}</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>{{ __('common.approved') }}</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>{{ __('common.rejected') }}</option>
                </select>
            </div>
        </div>
        <div class="btn-row">
            <input type="text" name="search" class="form-control" placeholder="{{ __('common.cnss_search_members_placeholder') }}" value="{{ request('search') }}" style="flex: 1;">
            <button type="submit" class="btn">{{ __('common.filter') }}</button>
            <a href="{{ route('business.cnss.members.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
        </div>
    </form>

    @if($enrollments->count() > 0)
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th>{{ __('common.cnss_voluntary_insured_number') }}</th>
                        <th>{{ __('common.cnss_affiliation_number') }}</th>
                        <th>{{ __('common.last_name') }}</th>
                        <th>{{ __('common.first_names') }}</th>
                        <th>{{ __('common.phone') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($enrollments as $row)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td><code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px;">{{ $row->voluntary_insured_number }}</code></td>
                            <td><code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px;">{{ $row->cnss_registration_affiliation_number }}</code></td>
                            <td>{{ $row->last_name ?? '—' }}</td>
                            <td>{{ $row->first_names ?? '—' }}</td>
                            <td>{{ $row->phone ?? '—' }}</td>
                            <td>{{ $row->status }}</td>
                            <td>
                                @php
                                    $canViewDetail = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-cnss-member', $businessUnit->id));
                                    $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-cnss-member', $businessUnit->id));
                                    $canDelete = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('delete-cnss-member', $businessUnit->id));
                                @endphp
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    @if($canViewDetail)
                                        <a href="{{ route('business.cnss.members.show', $row->id) }}" class="btn btn-sm">{{ __('common.view') }}</a>
                                    @endif
                                    @if($canEdit)
                                        <a href="{{ route('business.cnss.members.edit', $row->id) }}" class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                    @endif
                                    @if($canDelete)
                                        <form action="{{ route('business.cnss.members.destroy', $row->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
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
            {{ $enrollments->links() }}
        </div>
    @else
        <div class="muted-center">
            <p>{{ __('common.cnss_no_members') }}</p>
        </div>
    @endif
</div>
@endif
@endsection

@push('scripts')
<script>
function toggleExportDropdown() {
    const dropdown = document.getElementById('exportDropdown');
    if (dropdown) {
        dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    }
}

document.addEventListener('click', function(event) {
    const dropdown = document.getElementById('exportDropdown');
    const button = event.target.closest('button[onclick="toggleExportDropdown()"]');
    if (dropdown && !button && !dropdown.contains(event.target)) {
        dropdown.style.display = 'none';
    }
});
</script>
@endpush
