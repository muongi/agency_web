@extends('layouts.app')

@section('title', __('common.view_transaction_type'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.transaction_type_details') }}</h1>
        <div class="btn-row">
            <a href="{{ route('superadmin.transaction-types.edit', $transactionType->id) }}" class="btn">{{ __('common.edit') }}</a>
            <a href="{{ route('superadmin.transaction-types.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
        </div>
    </div>

    <div class="detail-grid">
        <div class="form-group">
            <label class="form-label">{{ __('common.name') }}</label>
            <div style="padding: 10px; background: var(--bg-secondary); border-radius: 4px;">{{ $transactionType->name }}</div>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('common.code') }}</label>
            <div style="padding: 10px; background: var(--bg-secondary); border-radius: 4px;"><code>{{ $transactionType->code }}</code></div>
        </div>

        <div class="form-group detail-full">
            <label class="form-label">{{ __('common.description') }}</label>
            <div style="padding: 10px; background: var(--bg-secondary); border-radius: 4px;">{{ $transactionType->description ?? '-' }}</div>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('common.status') }}</label>
            <div style="padding: 10px; background: var(--bg-secondary); border-radius: 4px;">
                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $transactionType->is_active ? 'var(--success-bg)' : 'var(--error-bg)' }}; color: {{ $transactionType->is_active ? 'var(--success-text)' : 'var(--error-text)' }};">
                    {{ $transactionType->is_active ? __('common.active') : __('common.inactive') }}
                </span>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('common.assigned_businesses') }}</label>
            <div style="padding: 10px; background: var(--bg-secondary); border-radius: 4px;">
                {{ $transactionType->businessUnits()->count() }} {{ __('common.business_units') }}
            </div>
        </div>

        @if($transactionType->businessUnits()->count() > 0)
        <div class="form-group detail-full">
            <label class="form-label">{{ __('common.business_units_list') }}</label>
            <div style="padding: 10px; background: var(--bg-secondary); border-radius: 4px;">
                <ul style="list-style: none; padding: 0; margin: 0;">
                    @foreach($transactionType->businessUnits as $businessUnit)
                        <li style="padding: 5px 0;">{{ $businessUnit->name }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
