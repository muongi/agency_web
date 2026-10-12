@extends('layouts.app')

@section('title', __('common.edit') . ' ' . __('common.role'))
@section('page-title', __('common.edit') . ' ' . __('common.role'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent);">{{ __('common.edit') }} {{ __('common.role') }}</h1>
    
    <form method="POST" action="{{ route('superadmin.user-management.roles.update', $role->id) }}">
        @csrf
        @method('PUT')

        <div class="detail-grid">
            <div class="form-group">
                <label class="form-label" for="name">{{ __('common.role_name') }} <span class="text-error">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $role->name) }}" required>
                @error('name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="business_unit_id">{{ __('common.business_unit') }}</label>
                <select id="business_unit_id" name="business_unit_id" class="form-select">
                    <option value="">{{ __('common.global') }} ({{ __('common.all_businesses') }})</option>
                    @foreach($businessUnits as $businessUnit)
                        <option value="{{ $businessUnit->id }}" {{ old('business_unit_id', $role->business_unit_id) == $businessUnit->id ? 'selected' : '' }}>
                            {{ $businessUnit->name }}
                        </option>
                    @endforeach
                </select>
                @error('business_unit_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <label class="form-label" for="description">{{ __('common.role_description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $role->description) }}</textarea>
                @error('description')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        @if($permissions->count() > 0)
        <div class="form-group" style="margin-top: 30px;">
            <label class="form-label">{{ __('common.permissions') }}</label>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
                @foreach($permissions as $group => $groupPermissions)
                    <div class="card" style="background: var(--bg-tertiary); padding: 15px;">
                        <h3 style="color: var(--accent); margin-bottom: 10px; font-size: 14px;">{{ \App\Models\Permission::groupLabel($group) }}</h3>
                        @foreach($groupPermissions as $permission)
                            <div class="checkbox-group" style="margin-bottom: 8px;">
                                <input type="checkbox" name="permissions[]" id="permission_{{ $permission->id }}" value="{{ $permission->id }}" {{ in_array($permission->id, old('permissions', $role->permissions->pluck('id')->toArray())) ? 'checked' : '' }}>
                                <label for="permission_{{ $permission->id }}" style="margin: 0; font-size: 14px;">{{ $permission->name }}</label>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="btn-row mt-24">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('superadmin.user-management.roles.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

