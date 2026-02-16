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
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: var(--accent-color);">{{ __('common.commission_details') }}</h1>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('business.config.commissions.edit', $commission->id) }}" class="btn btn-secondary">{{ __('common.edit') }}</a>
            <a href="{{ route('business.config.commissions.index') }}" class="btn">{{ __('common.back') }}</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.transaction_type') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">
                <strong>{{ $commission->transactionType->name }}</strong>
                <br>
                <code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $commission->transactionType->code }}</code>
            </p>
        </div>

        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.commission_type') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px; text-transform: capitalize;">{{ str_replace('_', ' ', $commission->commission_type) }}</p>
        </div>

        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.status') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">
                @if($commission->is_active)
                    <span style="color: var(--success-text);">{{ __('common.active') }}</span>
                @else
                    <span style="color: var(--error-text);">{{ __('common.inactive') }}</span>
                @endif
            </p>
        </div>

        @if($commission->commission_type === 'fixed')
        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.fixed_amount') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">{{ number_format($commission->fixed_amount, 2) }}</p>
        </div>
        @elseif($commission->commission_type === 'percentage')
        <div>
            <label style="color: var(--text-muted); font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">{{ __('common.percentage_rate') }}</label>
            <p style="color: var(--text-primary); font-size: 16px; margin-top: 5px;">{{ number_format($commission->percentage_rate, 2) }}%</p>
        </div>
        @endif
    </div>

    @if(in_array($commission->commission_type, ['tiered', 'tiered_percentage', 'tiered_fixed']))
    <div style="margin-top: 30px;">
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.commission_tiers') }}</h2>
        
        @if($commission->tiers->count() > 0)
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color);">
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.tier') }}</th>
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.min_amount') }}</th>
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.max_amount') }}</th>
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.rate') }} (%)</th>
                            <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.amount') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($commission->tiers->sortBy('order') as $index => $tier)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px;">{{ $index + 1 }}</td>
                                <td style="padding: 12px;">{{ $tier->min_amount ? number_format($tier->min_amount, 2) : '-' }}</td>
                                <td style="padding: 12px;">{{ $tier->max_amount ? number_format($tier->max_amount, 2) : __('common.unlimited') }}</td>
                                <td style="padding: 12px;">{{ $tier->rate ? number_format($tier->rate, 2) : '-' }}</td>
                                <td style="padding: 12px;">{{ $tier->amount ? number_format($tier->amount, 2) : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="color: var(--text-muted);">{{ __('common.no_tiers_configured') }}</p>
        @endif
    </div>
    @endif

    <!-- Commission Calculator -->
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

        // Calculate commission using the model's calculation method
        // We'll need to make an AJAX call or use the server-side calculation
        fetch('{{ route("business.config.commissions.show", $commission->id) }}?amount=' + amount, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.commission !== undefined) {
                resultDiv.textContent = `{{ __('common.commission') }}: ${data.commission.toFixed(2)}`;
            }
        })
        .catch(() => {
            // Fallback: calculate client-side
            const commissionType = '{{ $commission->commission_type }}';
            let commission = 0;

            if (commissionType === 'fixed') {
                commission = {{ $commission->fixed_amount ?? 0 }};
            } else if (commissionType === 'percentage') {
                const rate = {{ $commission->percentage_rate ?? 0 }};
                commission = amount * (rate / 100);
            } else if (['tiered', 'tiered_percentage', 'tiered_fixed'].includes(commissionType)) {
                const tiers = @json($commission->tiers->sortBy('order'));
                for (let tier of tiers) {
                    const minAmount = tier.min_amount || 0;
                    const maxAmount = tier.max_amount;
                    if (amount >= minAmount && (maxAmount === null || amount <= maxAmount)) {
                        if (commissionType === 'tiered_percentage' && tier.rate) {
                            commission = amount * (tier.rate / 100);
                        } else if (commissionType === 'tiered_fixed' && tier.amount) {
                            commission = tier.amount;
                        } else if (tier.rate) {
                            commission = amount * (tier.rate / 100);
                        } else {
                            commission = tier.amount || 0;
                        }
                        break;
                    }
                }
            }

            resultDiv.textContent = `{{ __('common.commission') }}: ${commission.toFixed(2)}`;
        });
    });
</script>
@endpush
@endsection

