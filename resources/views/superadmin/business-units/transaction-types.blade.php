@extends('layouts.app')

@section('title', __('common.assign_transaction_types'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: #4a9eff;">{{ __('common.assign_transaction_types') }} - {{ $businessUnit->name }}</h1>
        <a href="{{ route('superadmin.business-units.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
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

    <form method="POST" action="{{ route('superadmin.business-units.transaction-types.update', $businessUnit->id) }}">
        @csrf
        @method('PUT')

        @if($globalTransactionTypes->count() > 0)
            <div style="margin-bottom: 20px;">
                <p style="color: var(--text-muted); margin-bottom: 15px;">{{ __('common.select_transaction_types_for_business') }}</p>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                    @foreach($globalTransactionTypes as $transactionType)
                        <div style="padding: 15px; background: #2d2d2d; border-radius: 4px; border: 2px solid {{ in_array($transactionType->id, $assignedTransactionTypeIds) ? '#4a9eff' : '#404040' }};">
                            <label style="display: flex; align-items: start; cursor: pointer;">
                                <input 
                                    type="checkbox" 
                                    name="transaction_types[]" 
                                    value="{{ $transactionType->id }}"
                                    {{ in_array($transactionType->id, $assignedTransactionTypeIds) ? 'checked' : '' }}
                                    style="margin-right: 10px; margin-top: 3px;"
                                >
                                <div style="flex: 1;">
                                    <div style="font-weight: bold; color: #4a9eff; margin-bottom: 5px;">{{ $transactionType->name }}</div>
                                    <div style="font-size: 12px; color: #888; margin-bottom: 5px;">
                                        <code style="background: #1d1d1d; padding: 2px 6px; border-radius: 3px;">{{ $transactionType->code }}</code>
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

            <div style="display: flex; gap: 10px; margin-top: 30px;">
                <button type="submit" class="btn">{{ __('common.save_assignments') }}</button>
                <a href="{{ route('superadmin.business-units.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            </div>
        @else
            <div style="text-align: center; padding: 40px; color: #888;">
                <p style="margin-bottom: 20px;">{{ __('common.no_global_transaction_types') }}</p>
                <a href="{{ route('superadmin.transaction-types.create') }}" class="btn">{{ __('common.create_transaction_type') }}</a>
            </div>
        @endif
    </form>
</div>
@endsection
