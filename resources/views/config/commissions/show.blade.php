@extends('layouts.app')

@section('title', __('common.view_commission'))
@section('page-title', __('common.view_commission'))

@section('content')
<div class="card">
    <div style="background: var(--bg-tertiary); padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid var(--accent-color);">
        <p style="margin: 0; color: var(--text-primary); font-size: 14px;">
            <strong>{{ __('common.commission_calculation_note') }}</strong>
        </p>
    </div>
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.commission_details') }}</h1>
        <div class="btn-row">
            <a href="{{ route('business.config.commissions.edit', $commission->id) }}" class="btn btn-secondary">{{ __('common.edit') }}</a>
            <a href="{{ route('business.config.commissions.index') }}" class="btn">{{ __('common.back') }}</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div>
            <label class="section-label">{{ __('common.transaction_type') }}</label>
            <p class="detail-value">
                <strong>{{ $commission->transactionType->name }}</strong>
                <br>
                <code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $commission->transactionType->code }}</code>
            </p>
        </div>

        <div>
            <label class="section-label">{{ __('common.commission_type') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px; text-transform: capitalize;">{{ str_replace('_', ' ', $commission->commission_type) }}</p>
        </div>

        <div>
            <label class="section-label">{{ __('common.status') }}</label>
            <p class="detail-value">
                @if($commission->is_active)
                    <span class="badge badge-success">{{ __('common.active') }}</span>
                @else
                    <span class="text-error">{{ __('common.inactive') }}</span>
                @endif
            </p>
        </div>

        @if($commission->commission_type === 'fixed')
        <div>
            <label class="section-label">{{ __('common.fixed_amount') }}</label>
            <p class="detail-value">{{ number_format($commission->fixed_amount, 2) }}</p>
        </div>
        @elseif($commission->commission_type === 'percentage')
        <div>
            <label class="section-label">{{ __('common.percentage_rate') }}</label>
            <p class="detail-value">{{ number_format($commission->percentage_rate, 2) }}%</p>
        </div>
        @endif
    </div>

    @if(in_array($commission->commission_type, ['tiered', 'tiered_percentage', 'tiered_fixed']))
    <div style="margin-top: 30px;">
        <h2 style="color: var(--accent-color); margin-bottom: 10px;">{{ __('common.commission_tiers') }}</h2>
        <p class="text-muted-sm" style="margin-bottom: 20px;">{{ __('common.commission_tiers_inherit_from_cost') }}</p>
        
        @if($commission->tiers->count() > 0)
            <div class="data-table">
                <table>
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color);">
                            <th>{{ __('common.tier') }}</th>
                            <th>{{ __('common.min_amount') }}</th>
                            <th>{{ __('common.max_amount') }}</th>
                            <th>{{ __('common.rate') }} (%)</th>
                            <th>{{ __('common.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($commission->tiers->sortBy('order') as $index => $tier)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $tier->min_amount ? number_format($tier->min_amount, 2) : '-' }}</td>
                                <td>{{ $tier->max_amount ? number_format($tier->max_amount, 2) : __('common.unlimited') }}</td>
                                <td>{{ $tier->rate ? number_format($tier->rate, 2) : '-' }}</td>
                                <td>{{ $tier->amount ? number_format($tier->amount, 2) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="muted">{{ __('common.no_tiers_configured') }}</p>
        @endif
    </div>
    @endif

    <div style="margin-top: 30px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <h3 style="color: var(--accent-color); margin-bottom: 15px;">{{ __('common.commission_calculator') }}</h3>
        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
            <input type="number" id="calc_amount" class="form-control" placeholder="{{ __('common.enter_amount') }}" step="0.01" min="0" style="width: 200px;">
            <button type="button" id="calc_btn" class="btn">{{ __('common.calculate') }}</button>
            <div id="calc_result" style="color: var(--accent-color); font-size: 18px; font-weight: bold;"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.getElementById('calc_btn').addEventListener('click', function() {
        const amount = parseFloat(document.getElementById('calc_amount').value);
        const resultDiv = document.getElementById('calc_result');

        if (!amount || amount <= 0) {
            resultDiv.textContent = '';
            return;
        }

        fetch('{{ route("business.config.commissions.show", $commission->id) }}?amount=' + amount, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.commission !== undefined) {
                resultDiv.textContent = `{{ __('common.transaction_cost') }}: ${Number(data.transaction_cost).toFixed(2)} | {{ __('common.commission') }}: ${Number(data.commission).toFixed(2)}`;
            }
        })
        .catch(() => {
            resultDiv.textContent = '';
        });
    });
</script>
@endpush
@endsection
