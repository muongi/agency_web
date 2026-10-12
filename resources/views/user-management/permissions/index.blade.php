@extends('layouts.app')

@section('title', __('common.permissions'))
@section('page-title', __('common.permissions'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.permissions') }}</h1>
        <a href="{{ route('superadmin.user-management.permissions.create') }}" class="btn">{{ __('common.create_permission') }}</a>
    </div>

    @if($permissions->count() > 0)
        @foreach($permissions as $group => $groupPermissions)
            <div class="card mb-20">
                <h2 style="color: var(--accent); margin-bottom: 15px;">{{ \App\Models\Permission::groupLabel($group) }}</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                    @foreach($groupPermissions as $permission)
                        <div style="padding: 15px; background: var(--bg-tertiary); border-radius: 4px; border: 1px solid var(--border-color);">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <h3 style="color: var(--accent); margin-bottom: 5px;">{{ $permission->name }}</h3>
                                    @if($permission->description)
                                        <p style="color: var(--text-secondary); font-size: 14px;">{{ $permission->description }}</p>
                                    @endif
                                    <p style="color: var(--text-muted); font-size: 12px; margin-top: 5px;">{{ $permission->slug }}</p>
                                </div>
                                <a href="{{ route('superadmin.user-management.permissions.edit', $permission->id) }}" class="btn btn-sm">{{ __('common.edit') }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div class="muted-center">
            <p>{{ __('common.no_permissions') }}</p>
            <a href="{{ route('superadmin.user-management.permissions.create') }}" class="btn mt-20">{{ __('common.create_permission') }}</a>
        </div>
    @endif
</div>
@endsection

