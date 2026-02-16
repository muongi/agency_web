@extends('layouts.app')

@section('title', __('common.transaction_details'))
@section('page-title', __('common.transaction_details'))

@section('content')
@php
    $user = auth()->user();
    $businessUnit = $user->currentBusinessUnit;
    $canViewTransaction = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('view-transaction', $businessUnit->id));
@endphp

@if(!$canViewTransaction)
    <div class="card">
        <div style="text-align: center; padding: 40px; color: var(--error-text);">
            <p>{{ __('common.unauthorized_access') }}</p>
            <a href="{{ route('business.dashboard') }}" class="btn">{{ __('common.back_to_dashboard') }}</a>
        </div>
    </div>
@else
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: var(--accent-color);">{{ __('common.transaction_details') }}</h1>
        <a href="{{ route('transactions.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.transaction_date') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">
                {{ $transaction->transaction_date->format('Y-m-d H:i:s') }}
            </p>
        </div>

        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.transaction_type') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">
                <strong>{{ $transaction->transactionType->name }}</strong>
                <br>
                <code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $transaction->transactionType->code }}</code>
            </p>
        </div>

        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.status') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">
                @if($transaction->status === 'completed')
                    <span style="color: var(--success-text);">{{ __('common.completed') }}</span>
                @elseif($transaction->status === 'pending')
                    <span style="color: var(--warning-text);">{{ __('common.pending') }}</span>
                @elseif($transaction->status === 'failed')
                    <span style="color: var(--error-text);">{{ __('common.failed') }}</span>
                @else
                    <span style="color: var(--text-muted);">{{ __('common.cancelled') }}</span>
                @endif
            </p>
        </div>

        @if($transaction->reference)
        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.transaction_reference') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">
                <code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $transaction->reference }}</code>
            </p>
        </div>
        @endif
    </div>

    <!-- Transaction Amounts -->
    <div style="margin-bottom: 30px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.transaction_breakdown') }}</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div>
                <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.transaction_amount') }}</label>
                <p style="color: var(--text-primary); font-size: 24px; font-weight: bold; margin-top: 5px;">
                    {{ number_format($transaction->transaction_amount, 2) }}
                </p>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.transaction_cost') }}</label>
                <p style="color: var(--text-primary); font-size: 24px; font-weight: bold; margin-top: 5px;">
                    {{ number_format($transaction->transaction_cost, 2) }}
                </p>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.transaction_commission') }}</label>
                <p style="color: var(--success-text); font-size: 24px; font-weight: bold; margin-top: 5px;">
                    {{ number_format($transaction->commission, 2) }}
                </p>
            </div>
            <div>
                <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.business_share') }}</label>
                <p style="color: var(--accent-color); font-size: 24px; font-weight: bold; margin-top: 5px;">
                    {{ number_format($transaction->business_share, 2) }}
                </p>
            </div>
        </div>
    </div>

    <!-- Related Entities -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        @if($transaction->agent)
        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.agent') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">
                <strong>{{ $transaction->agent->full_name }}</strong>
                <br>
                <small style="color: var(--text-muted);">{{ $transaction->agent->phone_number }}</small>
            </p>
        </div>
        @endif

        @if($transaction->terminal)
        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.terminal') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">
                <strong>{{ $transaction->terminal->serial_number }}</strong>
                <br>
                <small style="color: var(--text-muted);">{{ $transaction->terminal->device_alias }}</small>
            </p>
        </div>
        @endif

        @if($transaction->client)
        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.client') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">
                <strong>{{ $transaction->client->name }}</strong>
                <br>
                <small style="color: var(--text-muted);">{{ $transaction->client->phone_number ?? '' }}</small>
            </p>
        </div>
        @endif
    </div>

    @if($transaction->metadata)
    <div style="margin-bottom: 30px;">
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.metadata') }}</h2>
        <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
            <pre style="color: var(--text-primary); font-size: 14px; margin: 0;">{{ json_encode($transaction->metadata, JSON_PRETTY_PRINT) }}</pre>
        </div>
    </div>
    @endif
</div>
@endif
@endsection

