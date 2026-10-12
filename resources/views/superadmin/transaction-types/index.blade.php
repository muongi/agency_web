@extends('layouts.app')

@section('title', __('common.transaction_types'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.global_transaction_types') }}</h1>
        <a href="{{ route('superadmin.transaction-types.create') }}" class="btn">+ {{ __('common.add_transaction_type') }}</a>
    </div>

    @if(session('success'))
        <div style="padding: 12px; margin-bottom: 20px; background: var(--success-bg); color: var(--success-text); border-radius: 4px;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="padding: 12px; margin-bottom: 20px; background: var(--error-bg); color: var(--error-text); border-radius: 4px;">
            {{ session('error') }}
        </div>
    @endif

    <!-- Search Form -->
    <form method="GET" action="{{ route('superadmin.transaction-types.index') }}" class="mb-20">
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
                <a href="{{ route('superadmin.transaction-types.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
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
                        <th>{{ __('common.assigned_businesses') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactionTypes as $transactionType)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>{{ $transactionType->name }}</td>
                            <td><code style="background: var(--bg-secondary); padding: 4px 8px; border-radius: 4px;">{{ $transactionType->code }}</code></td>
                            <td>{{ $transactionType->description ? \Illuminate\Support\Str::limit($transactionType->description, 50) : '-' }}</td>
                            <td>{{ $transactionType->businessUnits()->count() }}</td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $transactionType->is_active ? 'var(--success-bg)' : 'var(--error-bg)' }}; color: {{ $transactionType->is_active ? 'var(--success-text)' : 'var(--error-text)' }};">
                                    {{ $transactionType->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('superadmin.transaction-types.show', $transactionType->id) }}" class="btn btn-sm">{{ __('common.view') }}</a>
                                    <a href="{{ route('superadmin.transaction-types.edit', $transactionType->id) }}" class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                    <form action="{{ route('superadmin.transaction-types.destroy', $transactionType->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.are_you_sure') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">{{ __('common.delete') }}</button>
                                    </form>
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
            <a href="{{ route('superadmin.transaction-types.create') }}" class="btn">{{ __('common.add_transaction_type') }}</a>
        </div>
    @endif
</div>
@endsection
