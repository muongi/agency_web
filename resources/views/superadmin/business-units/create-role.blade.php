@extends('layouts.app')

@section('title', __('common.create_role'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: #4a9eff;">{{ __('common.create_role') }} - {{ $businessUnit->name }}</h1>
    
    <form method="POST" action="{{ route('superadmin.business-units.store-role', $businessUnit->id) }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="name">{{ __('common.role_name') }} <span style="color: #ff6b6b;">*</span></label>
            <input 
                type="text" 
                id="name" 
                name="name" 
                class="form-control" 
                value="{{ old('name') }}" 
                required 
                autofocus
                placeholder="e.g., Manager, Employee, Admin"
            >
            @error('name')
                <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="description">{{ __('common.role_description') }}</label>
            <textarea 
                id="description" 
                name="description" 
                class="form-control" 
                rows="4"
            >{{ old('description') }}</textarea>
            @error('description')
                <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
            @enderror
        </div>

        <div style="display: flex; gap: 10px; margin-top: 24px;">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('superadmin.business-units.users', $businessUnit->id) }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

