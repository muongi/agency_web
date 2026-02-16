@extends('layouts.app')

@section('title', __('common.sms_configuration'))
@section('page-title', __('common.sms_configuration'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: var(--accent-color);">{{ __('common.sms_configuration') }}</h1>
        <a href="{{ route('superadmin.sms.config.edit') }}" class="btn">{{ __('common.edit') }}</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div>
            <label style="color: var(--text-muted); font-size: 14px;">{{ __('common.client_id') }}</label>
            <div style="padding: 10px; background: var(--bg-tertiary); border-radius: 4px; margin-top: 5px; color: var(--text-primary);">
                {{ $config->client_id }}
            </div>
        </div>

        <div>
            <label style="color: var(--text-muted); font-size: 14px;">{{ __('common.api_endpoint') }}</label>
            <div style="padding: 10px; background: var(--bg-tertiary); border-radius: 4px; margin-top: 5px; color: var(--text-primary);">
                {{ $config->api_endpoint }}
            </div>
        </div>

        <div>
            <label style="color: var(--text-muted); font-size: 14px;">{{ __('common.sender_name') }}</label>
            <div style="padding: 10px; background: var(--bg-tertiary); border-radius: 4px; margin-top: 5px; color: var(--text-primary);">
                {{ $config->sender_name ?? __('common.not_set') }}
            </div>
        </div>

        <div>
            <label style="color: var(--text-muted); font-size: 14px;">{{ __('common.status') }}</label>
            <div style="padding: 10px; background: var(--bg-tertiary); border-radius: 4px; margin-top: 5px;">
                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                    {{ $config->is_active ? 'background: var(--success-bg); color: var(--success-text);' : 'background: var(--error-bg); color: var(--error-text);' }}">
                    {{ $config->is_active ? __('common.active') : __('common.inactive') }}
                </span>
            </div>
        </div>
    </div>

    <div style="margin-top: 30px;">
        <a href="{{ route('superadmin.sms.config.edit') }}" class="btn">{{ __('common.edit_configuration') }}</a>
    </div>
</div>
@endsection

