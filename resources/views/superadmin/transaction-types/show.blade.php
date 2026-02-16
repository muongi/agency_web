@extends('layouts.app')

@section('title', __('common.view_transaction_type'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: #4a9eff;">{{ __('common.transaction_type_details') }}</h1>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('superadmin.transaction-types.edit', $transactionType->id) }}" class="btn">{{ __('common.edit') }}</a>
            <a href="{{ route('superadmin.transaction-types.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
        <div class="form-group">
            <label class="form-label">{{ __('common.name') }}</label>
            <div style="padding: 10px; background: #2d2d2d; border-radius: 4px;">{{ $transactionType->name }}</div>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('common.code') }}</label>
            <div style="padding: 10px; background: #2d2d2d; border-radius: 4px;"><code>{{ $transactionType->code }}</code></div>
        </div>

        <div class="form-group" style="grid-column: 1 / -1;">
            <label class="form-label">{{ __('common.description') }}</label>
            <div style="padding: 10px; background: #2d2d2d; border-radius: 4px;">{{ $transactionType->description ?? '-' }}</div>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('common.status') }}</label>
            <div style="padding: 10px; background: #2d2d2d; border-radius: 4px;">
                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $transactionType->is_active ? '#2d5a2d' : '#5a2d2d' }}; color: {{ $transactionType->is_active ? '#90ee90' : '#ee9090' }};">
                    {{ $transactionType->is_active ? __('common.active') : __('common.inactive') }}
                </span>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">{{ __('common.assigned_businesses') }}</label>
            <div style="padding: 10px; background: #2d2d2d; border-radius: 4px;">
                {{ $transactionType->businessUnits()->count() }} {{ __('common.business_units') }}
            </div>
        </div>

        @if($transactionType->businessUnits()->count() > 0)
        <div class="form-group" style="grid-column: 1 / -1;">
            <label class="form-label">{{ __('common.business_units_list') }}</label>
            <div style="padding: 10px; background: #2d2d2d; border-radius: 4px;">
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
