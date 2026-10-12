@extends('layouts.app')

@section('title', __('common.edit') . ' ' . __('common.terminal'))
@section('page-title', __('common.edit') . ' ' . __('common.terminal'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.edit') }} {{ __('common.terminal') }}</h1>
    
    <form method="POST" action="{{ route('terminals.update', $terminal->id) }}">
        @csrf
        @method('PUT')

        <div class="detail-grid">
            <div class="form-group">
                <label class="form-label" for="serial_number">{{ __('common.serial_number') }} <span class="text-error">*</span></label>
                <input type="text" id="serial_number" name="serial_number" class="form-control" value="{{ old('serial_number', $terminal->serial_number) }}" required>
                @error('serial_number')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="simcard_number">{{ __('common.simcard_number') }} <span class="text-error">*</span></label>
                <input type="text" id="simcard_number" name="simcard_number" class="form-control" value="{{ old('simcard_number', $terminal->simcard_number) }}" required>
                @error('simcard_number')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="device_alias">{{ __('common.device_alias') }} <span class="text-error">*</span></label>
                <input type="text" id="device_alias" name="device_alias" class="form-control" value="{{ old('device_alias', $terminal->device_alias) }}" required>
                @error('device_alias')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="model_make">{{ __('common.model_make') }} <span class="text-error">*</span></label>
                <input type="text" id="model_make" name="model_make" class="form-control" value="{{ old('model_make', $terminal->model_make) }}" required>
                @error('model_make')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="btn-row mt-30">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('terminals.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

