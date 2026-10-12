@extends('layouts.app')

@section('title', __('common.edit') . ' ' . __('common.permission'))
@section('page-title', __('common.edit') . ' ' . __('common.permission'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent);">{{ __('common.edit') }} {{ __('common.permission') }}</h1>
    
    <form method="POST" action="{{ route('superadmin.user-management.permissions.update', $permission->id) }}">
        @csrf
        @method('PUT')

        <div class="detail-grid">
            <div class="form-group">
                <label class="form-label" for="name">{{ __('common.permission_name') }} <span class="text-error">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $permission->name) }}" required>
                @error('name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="group">{{ __('common.group') }}</label>
                <input type="text" id="group" name="group" class="form-control" value="{{ old('group', $permission->group) }}">
                @error('group')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <label class="form-label" for="description">{{ __('common.description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $permission->description) }}</textarea>
                @error('description')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="btn-row mt-24">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('superadmin.user-management.permissions.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

