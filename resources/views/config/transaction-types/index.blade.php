@extends('layouts.app')

@section('title', __('common.transaction_types'))
@section('page-title', __('common.transaction_types'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: var(--accent-color);">{{ __('common.transaction_types') }}</h1>
        <a href="{{ route('business.config.transaction-types.create') }}" class="btn">+ {{ __('common.add_transaction_type') }}</a>
    </div>

    <!-- Search Form -->
    <form method="GET" action="{{ route('business.config.transaction-types.index') }}" style="margin-bottom: 20px;">
        <div style="display: flex; gap: 10px;">
            <input 
                type="text" 
                name="search" 
                class="form-control" 
                placeholder="{{ __('common.search_transaction_types') }}..." 
                value="{{ request('search') }}"
                style="flex: 1;"
            >
            <button type="submit" class="btn">{{ __('common.search') }}</button>
            @if(request('search'))
                <a href="{{ route('business.config.transaction-types.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
            @endif
        </div>
    </form>

    @if($transactionTypes->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.name') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.code') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.description') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.type') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.status') }}</th>
                        <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactionTypes as $transactionType)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 12px;">{{ $transactionType->name }}</td>
                            <td style="padding: 12px;"><code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $transactionType->code }}</code></td>
                            <td style="padding: 12px;">{{ $transactionType->description ?? '-' }}</td>
                            <td style="padding: 12px;">
                                @if($transactionType->is_global)
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #2d4a5a; color: #90cdf4;">{{ __('common.global') }}</span>
                                @else
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #4a3d2d; color: #f4d090;">{{ __('common.business_specific') }}</span>
                                @endif
                            </td>
                            <td style="padding: 12px;">
                                @if($transactionType->is_active)
                                    <span style="color: var(--success-text);">{{ __('common.active') }}</span>
                                @else
                                    <span style="color: var(--error-text);">{{ __('common.inactive') }}</span>
                                @endif
                            </td>
                            <td style="padding: 12px;">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('business.config.transaction-types.show', $transactionType->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.view') }}</a>
                                    @if(!$transactionType->is_global)
                                        <a href="{{ route('business.config.transaction-types.edit', $transactionType->id) }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                                        <form action="{{ route('business.config.transaction-types.destroy', $transactionType->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">{{ __('common.delete') }}</button>
                                        </form>
                                    @else
                                        <span style="padding: 6px 12px; font-size: 12px; color: var(--text-muted);">{{ __('common.global_type') }}</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            {{ $transactionTypes->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <p style="margin-bottom: 20px;">{{ __('common.no_transaction_types') }}</p>
            <a href="{{ route('business.config.transaction-types.create') }}" class="btn">{{ __('common.add_transaction_type') }}</a>
        </div>
    @endif
</div>
@endsection

