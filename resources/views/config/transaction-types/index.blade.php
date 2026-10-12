@extends('layouts.app')

@section('title', __('common.transaction_types'))
@section('page-title', __('common.transaction_types'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 class="text-accent">{{ __('common.transaction_types') }}</h1>
        <a href="{{ route('business.config.transaction-types.create') }}" class="btn">+ {{ __('common.add_transaction_type') }}</a>
    </div>

    <!-- Search Form -->
    <form method="GET" action="{{ route('business.config.transaction-types.index') }}" class="mb-20">
        <div class="btn-row">
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
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('common.code') }}</th>
                        <th>{{ __('common.description') }}</th>
                        <th>{{ __('common.type') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactionTypes as $transactionType)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>{{ $transactionType->name }}</td>
                            <td><code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $transactionType->code }}</code></td>
                            <td>{{ $transactionType->description ?? '-' }}</td>
                            <td>
                                @if($transactionType->is_global)
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--info-bg); color: var(--info-text);">{{ __('common.global') }}</span>
                                @else
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--warning-bg); color: var(--warning-text);">{{ __('common.business_specific') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($transactionType->is_active)
                                    <span class="badge badge-success">{{ __('common.active') }}</span>
                                @else
                                    <span class="text-error">{{ __('common.inactive') }}</span>
                                @endif
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('business.config.transaction-types.show', $transactionType->id) }}" class="btn btn-sm">{{ __('common.view') }}</a>
                                    @if(!$transactionType->is_global)
                                        <a href="{{ route('business.config.transaction-types.edit', $transactionType->id) }}" class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                        <form action="{{ route('business.config.transaction-types.destroy', $transactionType->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">{{ __('common.delete') }}</button>
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

        <div class="mt-20">
            {{ $transactionTypes->links() }}
        </div>
    @else
        <div class="muted-center">
            <p class="mb-20">{{ __('common.no_transaction_types') }}</p>
            <a href="{{ route('business.config.transaction-types.create') }}" class="btn">{{ __('common.add_transaction_type') }}</a>
        </div>
    @endif
</div>
@endsection

