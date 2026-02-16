@extends('layouts.app')

@section('title', __('common.edit_sms_configuration'))
@section('page-title', __('common.edit_sms_configuration'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.edit_sms_configuration') }}</h1>
    
    <form method="POST" action="{{ route('superadmin.sms.config.update') }}">
        @csrf
        @method('PUT')
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="client_id">{{ __('common.client_id') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="client_id" name="client_id" class="form-control" value="{{ old('client_id', $config->client_id) }}" required>
                <small style="color: var(--text-muted); font-size: 12px;">{{ __('common.orange_api_client_id') }}</small>
                @error('client_id')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="client_secret">{{ __('common.client_secret') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="password" id="client_secret" name="client_secret" class="form-control" placeholder="{{ __('common.leave_blank_to_keep_current') }}">
                <small style="color: var(--text-muted); font-size: 12px;">{{ __('common.orange_api_client_secret') }}</small>
                @error('client_secret')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="jwt_token">{{ __('common.jwt_token') }}</label>
                <input type="password" id="jwt_token" name="jwt_token" class="form-control" placeholder="{{ __('common.leave_blank_to_keep_current') }}">
                <small style="color: var(--text-muted); font-size: 12px;">{{ __('common.optional_jwt_token') }}</small>
                @error('jwt_token')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="api_endpoint">{{ __('common.api_endpoint') }}</label>
                <input type="url" id="api_endpoint" name="api_endpoint" class="form-control" value="{{ old('api_endpoint', $config->api_endpoint) }}">
                <small style="color: var(--text-muted); font-size: 12px;">{{ __('common.orange_api_endpoint') }}</small>
                @error('api_endpoint')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="sender_name">{{ __('common.sender_name') }}</label>
                <input type="text" id="sender_name" name="sender_name" class="form-control" value="{{ old('sender_name', $config->sender_name) }}" maxlength="50">
                <small style="color: var(--text-muted); font-size: 12px;">{{ __('common.sms_sender_name') }}</small>
                @error('sender_name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="is_active">{{ __('common.is_active') }}</label>
                <select id="is_active" name="is_active" class="form-select">
                    <option value="1" {{ old('is_active', $config->is_active) ? 'selected' : '' }}>{{ __('common.active') }}</option>
                    <option value="0" {{ !old('is_active', $config->is_active) ? 'selected' : '' }}>{{ __('common.inactive') }}</option>
                </select>
                @error('is_active')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('superadmin.sms.config.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

