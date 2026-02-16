@extends('layouts.app')

@section('title', __('common.edit_transaction_type'))
@section('page-title', __('common.edit_transaction_type'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.edit_transaction_type') }}</h1>
    
    <form method="POST" action="{{ route('business.config.transaction-types.update', $transactionType->id) }}">
        @csrf
        @method('PUT')

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="name">{{ __('common.name') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $transactionType->name) }}" required>
                @error('name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="code">{{ __('common.code') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="code" name="code" class="form-control" value="{{ old('code', $transactionType->code) }}" required maxlength="50" style="text-transform: uppercase;">
                <small style="color: var(--text-muted); font-size: 12px;">{{ __('common.code_help') }}</small>
                @error('code')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="description">{{ __('common.description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $transactionType->description) }}</textarea>
                @error('description')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $transactionType->is_active) ? 'checked' : '' }}>
                    <label for="is_active">{{ __('common.is_active') }}</label>
                </div>
                @error('is_active')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('business.config.transaction-types.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.getElementById('code').addEventListener('input', function(e) {
        e.target.value = e.target.value.toUpperCase();
    });
</script>
@endpush
@endsection

