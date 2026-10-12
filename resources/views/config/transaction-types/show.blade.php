@extends('layouts.app')

@section('title', __('common.view_transaction_type'))
@section('page-title', __('common.view_transaction_type'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.transaction_type_details') }}</h1>
        <div class="btn-row">
            <a href="{{ route('business.config.transaction-types.edit', $transactionType->id) }}" class="btn btn-secondary">{{ __('common.edit') }}</a>
            <a href="{{ route('business.config.transaction-types.index') }}" class="btn">{{ __('common.back') }}</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div>
            <label class="section-label">{{ __('common.name') }}</label>
            <p class="detail-value">{{ $transactionType->name }}</p>
        </div>

        <div>
            <label class="section-label">{{ __('common.code') }}</label>
            <p class="detail-value"><code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $transactionType->code }}</code></p>
        </div>

        <div>
            <label class="section-label">{{ __('common.status') }}</label>
            <p class="detail-value">
                @if($transactionType->is_active)
                    <span class="badge badge-success">{{ __('common.active') }}</span>
                @else
                    <span class="text-error">{{ __('common.inactive') }}</span>
                @endif
            </p>
        </div>

        @if($transactionType->description)
        <div class="detail-full">
            <label class="section-label">{{ __('common.description') }}</label>
            <p class="detail-value">{{ $transactionType->description }}</p>
        </div>
        @endif
    </div>
</div>
@endsection

