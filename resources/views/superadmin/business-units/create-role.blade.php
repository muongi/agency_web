@extends('layouts.app')

@section('title', __('common.create_role'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent);">{{ __('common.create_role') }} - {{ $businessUnit->name }}</h1>
    
    <form method="POST" action="{{ route('superadmin.business-units.store-role', $businessUnit->id) }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="name">{{ __('common.role_name') }} <span class="text-error">*</span></label>
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
                <div class="field-error">{{ $message }}</div>
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
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="btn-row mt-24">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('superadmin.business-units.users', $businessUnit->id) }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

