@extends('layouts.app')

@section('title', __('common.permissions'))
@section('page-title', __('common.permissions'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: #4a9eff;">{{ __('common.permissions') }}</h1>
        <a href="{{ route('superadmin.user-management.permissions.create') }}" class="btn">{{ __('common.create_permission') }}</a>
    </div>

    @if($permissions->count() > 0)
        @foreach($permissions as $group => $groupPermissions)
            <div class="card" style="margin-bottom: 20px;">
                <h2 style="color: #4a9eff; margin-bottom: 15px;">{{ $group ?: __('common.other') }}</h2>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                    @foreach($groupPermissions as $permission)
                        <div style="padding: 15px; background: #3d3d3d; border-radius: 4px; border: 1px solid #404040;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <h3 style="color: #4a9eff; margin-bottom: 5px;">{{ $permission->name }}</h3>
                                    @if($permission->description)
                                        <p style="color: #aaa; font-size: 14px;">{{ $permission->description }}</p>
                                    @endif
                                    <p style="color: #666; font-size: 12px; margin-top: 5px;">{{ $permission->slug }}</p>
                                </div>
                                <a href="{{ route('superadmin.user-management.permissions.edit', $permission->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @else
        <div style="text-align: center; padding: 40px; color: #888;">
            <p>{{ __('common.no_permissions') }}</p>
            <a href="{{ route('superadmin.user-management.permissions.create') }}" class="btn" style="margin-top: 20px;">{{ __('common.create_permission') }}</a>
        </div>
    @endif
</div>
@endsection

