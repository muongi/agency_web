@extends('layouts.app')

@section('title', __('common.transaction_cost_setup'))
@section('page-title', __('common.transaction_cost_setup'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 class="text-accent">{{ __('common.transaction_cost_setup') }}</h1>
        @if($transactionTypes->count() > 0)
            <a href="{{ route('business.config.transaction-costs.create') }}" class="btn">+ {{ __('common.add_transaction_cost') }}</a>
        @endif
    </div>

    @if($transactionTypes->count() === 0)
        <div class="muted-center">
            <p class="mb-20">{{ __('common.no_assigned_transaction_types') }}</p>
            <p style="margin-bottom: 20px; font-size: 14px;">{{ __('common.contact_superadmin_to_assign_types') }}</p>
        </div>
    @else
        <!-- Search Form -->
        <form method="GET" action="{{ route('business.config.transaction-costs.index') }}" class="mb-20">
            <div class="btn-row">
                <input 
                    type="text" 
                    name="search" 
                    class="form-control" 
                    placeholder="{{ __('common.search_transaction_costs') }}..." 
                    value="{{ request('search') }}"
                    style="flex: 1;"
                >
                <button type="submit" class="btn">{{ __('common.search') }}</button>
                @if(request('search'))
                    <a href="{{ route('business.config.transaction-costs.index') }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
                @endif
            </div>
        </form>

        @if($costConfigs->count() > 0)
            <div class="data-table">
                <table>
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color);">
                            <th>{{ __('common.transaction_type') }}</th>
                            <th>{{ __('common.cost_type') }}</th>
                            <th>{{ __('common.cost_value') }}</th>
                            <th>{{ __('common.status') }}</th>
                            <th>{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($costConfigs as $costConfig)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td>
                                    <strong>{{ $costConfig->transactionType->name }}</strong>
                                    <br>
                                    <code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px; font-size: 11px;">{{ $costConfig->transactionType->code }}</code>
                                </td>
                                <td>
                                    <span style="text-transform: capitalize;">{{ str_replace('_', ' ', $costConfig->cost_type) }}</span>
                                    @if(in_array($costConfig->cost_type, ['tiered_fixed', 'tiered_percentage']))
                                        <br>
                                        <small class="muted">({{ $costConfig->tiers->count() }} {{ __('common.tiers') }})</small>
                                    @endif
                                </td>
                                <td>
                                    @if($costConfig->cost_type === 'fixed')
                                        {{ number_format($costConfig->fixed_amount, 2) }}
                                    @elseif($costConfig->cost_type === 'percentage')
                                        {{ number_format($costConfig->percentage_rate, 2) }}%
                                    @else
                                        {{ __('common.tiered_structure') }}
                                    @endif
                                </td>
                                <td>
                                    @if($costConfig->is_active)
                                        <span class="badge badge-success">{{ __('common.active') }}</span>
                                    @else
                                        <span class="text-error">{{ __('common.inactive') }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px;">
                                        <a href="{{ route('business.config.transaction-costs.show', $costConfig->id) }}" class="btn btn-sm">{{ __('common.view') }}</a>
                                        <a href="{{ route('business.config.transaction-costs.edit', $costConfig->id) }}" class="btn btn-secondary btn-sm">{{ __('common.edit') }}</a>
                                        <form action="{{ route('business.config.transaction-costs.destroy', $costConfig->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
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
                {{ $costConfigs->links() }}
            </div>
        @else
            <div class="muted-center">
                <p class="mb-20">{{ __('common.no_transaction_costs') }}</p>
                <a href="{{ route('business.config.transaction-costs.create') }}" class="btn">{{ __('common.add_transaction_cost') }}</a>
            </div>
        @endif
    @endif
</div>
@endsection

