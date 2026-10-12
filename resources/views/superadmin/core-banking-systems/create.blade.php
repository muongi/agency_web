@extends('layouts.app')

@section('title', __('common.create_cbs'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent);">{{ __('common.create_cbs') }}</h1>
    
    <form method="POST" action="{{ route('superadmin.core-banking-systems.store') }}">
        @csrf

        <div class="form-group">
            <label class="form-label" for="name">{{ __('common.cbs_name') }} <span class="text-error">*</span></label>
            <input 
                type="text" 
                id="name" 
                name="name" 
                class="form-control" 
                value="{{ old('name') }}" 
                required 
                autofocus
            >
            @error('name')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="code">{{ __('common.cbs_code') }}</label>
            <input 
                type="text" 
                id="code" 
                name="code" 
                class="form-control" 
                value="{{ old('code') }}"
                placeholder="e.g., T24, Flexcube"
                maxlength="50"
            >
            <small style="color: var(--text-muted); font-size: 12px; margin-top: 5px; display: block;">{{ __('common.cbs_code_help') }}</small>
            @error('code')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="description">{{ __('common.cbs_description') }}</label>
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

        <div class="form-group">
            <label class="form-label" for="base_url">{{ __('common.kato_base_url') }}</label>
            <input 
                type="url" 
                id="base_url" 
                name="base_url" 
                class="form-control" 
                value="{{ old('base_url') }}"
                placeholder="https://api.saccoworks.co.ke/api/v2/agency"
            >
            <small style="color: var(--text-muted); font-size: 12px; margin-top: 5px; display: block;">{{ __('common.kato_base_url_help') }}</small>
            @error('base_url')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        @php $cbs = new \App\Models\CoreBankingSystem(); @endphp
        @include('superadmin.core-banking-systems._auth_fields')

        <div class="form-group">
            <label class="form-label" for="is_active">
                <input 
                    type="checkbox" 
                    id="is_active" 
                    name="is_active" 
                    value="1"
                    {{ old('is_active', true) ? 'checked' : '' }}
                >
                {{ __('common.is_active') }}
            </label>
            @error('is_active')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="btn-row mt-24">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('superadmin.core-banking-systems.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection