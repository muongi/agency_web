@extends('layouts.app')

@section('title', __('common.create_role'))
@section('page-title', __('common.create_role'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: #4a9eff;">{{ __('common.create_role') }}</h1>
    
    <form method="POST" action="{{ route('superadmin.user-management.roles.store') }}">
        @csrf

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="name">{{ __('common.role_name') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required>
                @error('name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="business_unit_id">{{ __('common.business_unit') }}</label>
                <select id="business_unit_id" name="business_unit_id" class="form-select">
                    <option value="">{{ __('common.global') }} ({{ __('common.all_businesses') }})</option>
                    @foreach($businessUnits as $businessUnit)
                        <option value="{{ $businessUnit->id }}" {{ old('business_unit_id') == $businessUnit->id ? 'selected' : '' }}>
                            {{ $businessUnit->name }}
                        </option>
                    @endforeach
                </select>
                @error('business_unit_id')
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
            <label class="form-label">{{ __('common.permissions') }}</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                @foreach($permissions as $group => $groupPermissions)
                    <div class="card" style="background: #3d3d3d; padding: 15px;">
                        <h3 style="color: #4a9eff; margin-bottom: 10px; font-size: 14px;">{{ $group ?: __('common.other') }}</h3>
                        @foreach($groupPermissions as $permission)
                            <div class="checkbox-group" style="margin-bottom: 8px;">
                                <input type="checkbox" name="permissions[]" id="permission_{{ $permission->id }}" value="{{ $permission->id }}" {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                                <label for="permission_{{ $permission->id }}" style="margin: 0; font-size: 14px;">{{ $permission->name }}</label>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <div style="display: flex; gap: 10px; margin-top: 24px;">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('superadmin.user-management.roles.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

