@extends('layouts.app')

@section('title', __('common.view_transaction_type'))
@section('page-title', __('common.view_transaction_type'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: var(--accent-color);">{{ __('common.transaction_type_details') }}</h1>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('business.config.transaction-types.edit', $transactionType->id) }}" class="btn btn-secondary">{{ __('common.edit') }}</a>
            <a href="{{ route('business.config.transaction-types.index') }}" class="btn">{{ __('common.back') }}</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.name') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">{{ $transactionType->name }}</p>
        </div>

        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.code') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;"><code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $transactionType->code }}</code></p>
        </div>

        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.status') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">
                @if($transactionType->is_active)
                    <span style="color: var(--success-text);">{{ __('common.active') }}</span>
                @else
                    <span style="color: var(--error-text);">{{ __('common.inactive') }}</span>
                @endif
            </p>
        </div>

        @if($transactionType->description)
        <div style="grid-column: 1 / -1;">
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.description') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">{{ $transactionType->description }}</p>
        </div>
        @endif
    </div>
</div>
@endsection

