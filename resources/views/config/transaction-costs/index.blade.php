@extends('layouts.app')

@section('title', __('common.transaction_cost_setup'))
@section('page-title', __('common.transaction_cost_setup'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 style="color: var(--accent-color);">{{ __('common.transaction_cost_setup') }}</h1>
        @if($transactionTypes->count() > 0)
            <a href="{{ route('business.config.transaction-costs.create') }}" class="btn">+ {{ __('common.add_transaction_cost') }}</a>
        @endif
    </div>

    @if($transactionTypes->count() === 0)
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <p style="margin-bottom: 20px;">{{ __('common.no_transaction_types_available') }}</p>
            <a href="{{ route('business.config.transaction-types.create') }}" class="btn">{{ __('common.create_transaction_type_first') }}</a>
        </div>
    @else
        <!-- Search Form -->
        <form method="GET" action="{{ route('business.config.transaction-costs.index') }}" style="margin-bottom: 20px;">
            <div style="display: flex; gap: 10px;">
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
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color);">
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.transaction_type') }}</th>
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.cost_type') }}</th>
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.cost_value') }}</th>
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.status') }}</th>
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($costConfigs as $costConfig)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px;">
                                    <strong>{{ $costConfig->transactionType->name }}</strong>
                                    <br>
                                    <code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px; font-size: 11px;">{{ $costConfig->transactionType->code }}</code>
                                </td>
                                <td style="padding: 12px;">
                                    <span style="text-transform: capitalize;">{{ str_replace('_', ' ', $costConfig->cost_type) }}</span>
                                    @if(in_array($costConfig->cost_type, ['tiered_fixed', 'tiered_percentage']))
                                        <br>
                                        <small style="color: var(--text-muted);">({{ $costConfig->tiers->count() }} {{ __('common.tiers') }})</small>
                                    @endif
                                </td>
                                <td style="padding: 12px;">
                                    @if($costConfig->cost_type === 'fixed')
                                        {{ number_format($costConfig->fixed_amount, 2) }}
                                    @elseif($costConfig->cost_type === 'percentage')
                                        {{ number_format($costConfig->percentage_rate, 2) }}%
                                    @else
                                        {{ __('common.tiered_structure') }}
                                    @endif
                                </td>
                                <td style="padding: 12px;">
                                    @if($costConfig->is_active)
                                        <span style="color: var(--success-text);">{{ __('common.active') }}</span>
                                    @else
                                        <span style="color: var(--error-text);">{{ __('common.inactive') }}</span>
                                    @endif
                                </td>
                                <td style="padding: 12px;">
                                    <div style="display: flex; gap: 5px;">
                                        <a href="{{ route('business.config.transaction-costs.show', $costConfig->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.view') }}</a>
                                        <a href="{{ route('business.config.transaction-costs.edit', $costConfig->id) }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                                        <form action="{{ route('business.config.transaction-costs.destroy', $costConfig->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
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
                {{ $costConfigs->links() }}
            </div>
        @else
            <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                <p style="margin-bottom: 20px;">{{ __('common.no_transaction_costs') }}</p>
                <a href="{{ route('business.config.transaction-costs.create') }}" class="btn">{{ __('common.add_transaction_cost') }}</a>
            </div>
        @endif
    @endif
</div>
@endsection

