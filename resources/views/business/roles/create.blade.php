@extends('layouts.app')

@section('title', __('common.create_role'))
@section('page-title', __('common.create_role') . ' - ' . $businessUnit->name)

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: #4a9eff;">{{ __('common.create_role') }} - {{ $businessUnit->name }}</h1>
    
    <form method="POST" action="{{ route('business.roles.store') }}">
        @csrf

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="name">{{ __('common.role_name') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required autofocus placeholder="e.g., Manager, Employee, Admin">
                @error('name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="description">{{ __('common.role_description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                @error('description')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        @if($permissions->count() > 0)
        <div class="form-group" style="margin-top: 30px;">
            <label class="form-label" style="font-size: 18px; margin-bottom: 15px;">{{ __('common.select_permissions') }}</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-top: 15px;">
                @foreach($permissions as $group => $groupPermissions)
                    <div class="card" style="background: #3d3d3d; padding: 15px;">
                        <h3 style="color: #4a9eff; margin-bottom: 12px; font-size: 14px; text-transform: uppercase; letter-spacing: 1px;">{{ $group ?: __('common.other') }}</h3>
                        @foreach($groupPermissions as $permission)
                            <div class="checkbox-group" style="margin-bottom: 10px; padding: 8px; background: #2d2d2d; border-radius: 4px;">
                                <input type="checkbox" name="permissions[]" id="permission_{{ $permission->id }}" value="{{ $permission->id }}" {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                                <label for="permission_{{ $permission->id }}" style="margin: 0; font-size: 14px; cursor: pointer;">
                                    @php
                                        $permissionNameKey = 'common.permission_' . $permission->slug;
                                        $permissionName = trans($permissionNameKey);
                                        $permissionName = ($permissionName === $permissionNameKey) ? $permission->name : $permissionName;
                                        
                                        $permissionDescKey = 'common.permission_' . $permission->slug . '_desc';
                                        $permissionDesc = trans($permissionDescKey);
                                        $permissionDesc = ($permissionDesc === $permissionDescKey) ? $permission->description : $permissionDesc;
                                    @endphp
                                    <strong>{{ $permissionName }}</strong>
                                    @if($permission->description)
                                        <br><span style="color: #888; font-size: 12px;">{{ $permissionDesc }}</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        @else
        <div style="padding: 20px; background: #3d3d3d; border-radius: 4px; margin-top: 20px; text-align: center; color: #888;">
            <p>{{ __('common.no_permissions_available') }}</p>
            <p style="font-size: 12px; margin-top: 10px;">{{ __('common.contact_super_admin') }}</p>
        </div>
        @endif

        <div style="display: flex; gap: 10px; margin-top: 24px;">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('business.roles.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

