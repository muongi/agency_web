@extends('layouts.app')

@section('title', __('common.transaction_types'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: #4a9eff;">{{ __('common.global_transaction_types') }}</h1>
        <a href="{{ route('superadmin.transaction-types.create') }}" class="btn">+ {{ __('common.add_transaction_type') }}</a>
    </div>

    @if(session('success'))
        <div style="padding: 12px; margin-bottom: 20px; background: #2d5a2d; color: #90ee90; border-radius: 4px;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="padding: 12px; margin-bottom: 20px; background: #5a2d2d; color: #ee9090; border-radius: 4px;">
            {{ session('error') }}
        </div>
    @endif

    <!-- Search Form -->
    <form method="GET" action="{{ route('superadmin.transaction-types.index') }}" style="margin-bottom: 20px;">
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
                <a href="{{ route('superadmin.transaction-types.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
            @endif
        </div>
    </form>

    @if($transactionTypes->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #404040;">
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.name') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.code') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.description') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.assigned_businesses') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.status') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactionTypes as $transactionType)
                        <tr style="border-bottom: 1px solid #404040;">
                            <td style="padding: 12px;">{{ $transactionType->name }}</td>
                            <td style="padding: 12px;"><code style="background: #2d2d2d; padding: 4px 8px; border-radius: 4px;">{{ $transactionType->code }}</code></td>
                            <td style="padding: 12px;">{{ $transactionType->description ? \Illuminate\Support\Str::limit($transactionType->description, 50) : '-' }}</td>
                            <td style="padding: 12px;">{{ $transactionType->businessUnits()->count() }}</td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $transactionType->is_active ? '#2d5a2d' : '#5a2d2d' }}; color: {{ $transactionType->is_active ? '#90ee90' : '#ee9090' }};">
                                    {{ $transactionType->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('superadmin.transaction-types.show', $transactionType->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.view') }}</a>
                                    <a href="{{ route('superadmin.transaction-types.edit', $transactionType->id) }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                                    <form action="{{ route('superadmin.transaction-types.destroy', $transactionType->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.are_you_sure') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">{{ __('common.delete') }}</button>
                                    </form>
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
        <div style="text-align: center; padding: 40px; color: #888;">
            <p style="margin-bottom: 20px;">{{ __('common.no_transaction_types') }}</p>
            <a href="{{ route('superadmin.transaction-types.create') }}" class="btn">{{ __('common.add_transaction_type') }}</a>
        </div>
    @endif
</div>
@endsection
