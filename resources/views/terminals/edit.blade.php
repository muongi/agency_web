@extends('layouts.app')

@section('title', __('common.edit') . ' ' . __('common.terminal'))
@section('page-title', __('common.edit') . ' ' . __('common.terminal'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.edit') }} {{ __('common.terminal') }}</h1>
    
    <form method="POST" action="{{ route('terminals.update', $terminal->id) }}">
        @csrf
        @method('PUT')

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="serial_number">{{ __('common.serial_number') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="serial_number" name="serial_number" class="form-control" value="{{ old('serial_number', $terminal->serial_number) }}" required>
                @error('serial_number')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="simcard_number">{{ __('common.simcard_number') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="simcard_number" name="simcard_number" class="form-control" value="{{ old('simcard_number', $terminal->simcard_number) }}" required>
                @error('simcard_number')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="device_alias">{{ __('common.device_alias') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="device_alias" name="device_alias" class="form-control" value="{{ old('device_alias', $terminal->device_alias) }}" required>
                @error('device_alias')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="model_make">{{ __('common.model_make') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="model_make" name="model_make" class="form-control" value="{{ old('model_make', $terminal->model_make) }}" required>
                @error('model_make')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('terminals.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

