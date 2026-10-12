@extends('layouts.app')

@section('title', __('common.assign_transaction_types'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.assign_transaction_types') }} - {{ $businessUnit->name }}</h1>
        <a href="{{ route('superadmin.business-units.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
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

    <form method="POST" action="{{ route('superadmin.business-units.transaction-types.update', $businessUnit->id) }}">
        @csrf
        @method('PUT')

        @if($globalTransactionTypes->count() > 0)
            <div class="mb-20">
                <p style="color: var(--text-muted); margin-bottom: 15px;">{{ __('common.select_transaction_types_for_business') }}</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                    @foreach($globalTransactionTypes as $transactionType)
                        <div style="padding: 15px; background: var(--bg-secondary); border-radius: 4px; border: 2px solid {{ in_array($transactionType->id, $assignedTransactionTypeIds) ? 'var(--accent)' : 'var(--border-color)' }};">
                            <label style="display: flex; align-items: start; cursor: pointer;">
                                <input 
                                    type="checkbox" 
                                    name="transaction_types[]" 
                                    value="{{ $transactionType->id }}"
                                    {{ in_array($transactionType->id, $assignedTransactionTypeIds) ? 'checked' : '' }}
                                    style="margin-right: 10px; margin-top: 3px;"
                                >
                                <div style="flex: 1;">
                                    <div style="font-weight: bold; color: var(--accent); margin-bottom: 5px;">{{ $transactionType->name }}</div>
                                    <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">
                                        <code style="background: var(--bg-primary); padding: 2px 6px; border-radius: 3px;">{{ $transactionType->code }}</code>
                                    </div>
                                    @if($transactionType->description)
                                        <div style="font-size: 12px; color: var(--text-muted);">{{ \Illuminate\Support\Str::limit($transactionType->description, 80) }}</div>
                                    @endif
                                </div>
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="btn-row mt-30">
                <button type="submit" class="btn">{{ __('common.save_assignments') }}</button>
                <a href="{{ route('superadmin.business-units.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            </div>
        @else
            <div class="muted-center">
                <p class="mb-20">{{ __('common.no_global_transaction_types') }}</p>
                <a href="{{ route('superadmin.transaction-types.create') }}" class="btn">{{ __('common.create_transaction_type') }}</a>
            </div>
        @endif
    </form>
</div>
@endsection
