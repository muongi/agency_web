@extends('layouts.app')

@section('title', __('common.dashboard'))

@section('content')
@if($businessUnit)
<x-page-header :title="__('common.dashboard')" :subtitle="$businessUnit->name" />

<div class="card">
    <div style="margin-bottom: 30px; padding: 20px; background: var(--bg-tertiary); border-radius: var(--radius); border: 1px solid var(--border-color);">
        <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 15px;">
            @if($businessUnit->logo)
                <img src="{{ asset('storage/' . $businessUnit->logo) }}" alt="{{ $businessUnit->name }}" class="business-logo">
            @endif
            <div>
                <h2 style="color: var(--accent-color); margin-bottom: 5px;">{{ $businessUnit->name }}</h2>
                @if($businessUnit->account)
                    <p style="color: var(--text-secondary); font-size: 14px;">{{ __('common.account') }}: {{ $businessUnit->account }}</p>
                @endif
            </div>
        </div>
        @if($businessUnit->description)
            <p style="color: var(--text-secondary); margin-bottom: 10px;">{{ $businessUnit->description }}</p>
        @endif
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 15px;">
            @if($businessUnit->email)
                <div>
                    <strong class="text-accent">{{ __('common.email') }}:</strong>
                    <p style="color: var(--text-primary);">{{ $businessUnit->email }}</p>
                </div>
            @endif
            @if($businessUnit->phone)
                <div>
                    <strong class="text-accent">{{ __('common.phone') }}:</strong>
                    <p style="color: var(--text-primary);">{{ $businessUnit->phone }}</p>
                </div>
            @endif
            @if($businessUnit->address)
                <div>
                    <strong class="text-accent">{{ __('common.address') }}:</strong>
                    <p style="color: var(--text-primary);">{{ $businessUnit->address }}</p>
                </div>
            @endif
            @if($businessUnit->tax_number)
                <div>
                    <strong class="text-accent">{{ __('common.tax_number') }}:</strong>
                    <p style="color: var(--text-primary);">{{ $businessUnit->tax_number }}</p>
                </div>
            @endif
            @if($businessUnit->mailing_address)
                <div>
                    <strong class="text-accent">{{ __('common.mailing_address') }}:</strong>
                    <p style="color: var(--text-primary);">{{ $businessUnit->mailing_address }}</p>
                </div>
            @endif
            @if($businessUnit->relation_manager_phone)
                <div>
                    <strong class="text-accent">{{ __('common.relation_manager_phone') }}:</strong>
                    <p style="color: var(--text-primary);">{{ $businessUnit->relation_manager_phone }}</p>
                </div>
            @endif
            @if($businessUnit->relation_manager_email)
                <div>
                    <strong class="text-accent">{{ __('common.relation_manager_email') }}:</strong>
                    <p style="color: var(--text-primary);">{{ $businessUnit->relation_manager_email }}</p>
                </div>
            @endif
        </div>
    </div>

    <div class="stat-grid">
        <x-stat-card icon="users" :label="__('common.users')" :value="$businessUnit->users->count()">
            <a href="{{ route('business.users.index') }}" class="btn btn-sm" style="margin-top: 10px; align-self: flex-start;">{{ __('common.manage_users') }}</a>
        </x-stat-card>
        <x-stat-card icon="shield" :label="__('common.roles')" :value="$businessUnit->roles->count()">
            <a href="{{ route('business.roles.index') }}" class="btn btn-sm" style="margin-top: 10px; align-self: flex-start;">{{ __('common.manage_roles') }}</a>
        </x-stat-card>
    </div>

    @if(isset($transactionStats))
    <!-- Monthly Transaction Chart -->
    @if(isset($monthlyData) && count($monthlyData) > 0)
    <div class="card" style="margin-top: 30px;">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px;">
            <h2 style="color: var(--accent-color); margin: 0; display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">📊</span>
                <span>{{ __('common.transactions') }} - {{ __('common.monthly_for_year') }} {{ $currentYear }}</span>
            </h2>
        </div>
        <div style="position: relative; height: 400px; padding: 20px; background: var(--bg-primary); border-radius: 8px;">
            <canvas id="monthlyTransactionChart"></canvas>
        </div>
    </div>
    @endif

    <!-- Transaction Summary Section -->
    <div style="margin-top: 30px;">
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.transaction_summary') }} - {{ __('common.current_month') }}</h2>
        
        @php $cur = config('currency.default', 'CFA'); @endphp
        <div class="stat-grid">
            <x-stat-card icon="card" :label="__('common.total_transactions_month')"
                         :value="number_format($transactionStats['total_transactions'])">
                <a href="{{ route('transactions.index') }}" class="btn btn-sm" style="margin-top: 10px; align-self: flex-start;">{{ __('common.view_all') }}</a>
            </x-stat-card>
            <x-stat-card icon="wallet" :label="__('common.total_transaction_amount')"
                         :value="number_format($transactionStats['total_amount'], 2)">
                <small class="muted">{{ $cur }}</small>
            </x-stat-card>
            <x-stat-card icon="chart" :label="__('common.total_commissions_paid')"
                         :value="number_format($transactionStats['total_commissions'], 2)">
                <small class="muted">{{ $cur }}</small>
            </x-stat-card>
            <x-stat-card icon="building" :label="__('common.business_revenue')"
                         :value="number_format($transactionStats['total_business_share'], 2)">
                <small class="muted">{{ $cur }}</small>
            </x-stat-card>
        </div>

        <!-- Top Agents by Commission -->
        @if($topAgents->count() > 0)
        <div class="card mt-20">
            <h2 style="margin-bottom: 20px; color: var(--accent-color);">{{ __('common.top_agents_by_commission') }}</h2>
            <div class="data-table">
                <table>
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color);">
                            <th>#</th>
                            <th>{{ __('common.agent_name') }}</th>
                            <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.number_of_transactions') }}</th>
                            <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.total_commission_earned') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topAgents as $index => $item)
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 12px; color: var(--text-muted);">{{ $index + 1 }}</td>
                                <td>
                                    @if($item['agent'])
                                        <a href="{{ route('agents.show', $item['agent']->id) }}" style="color: var(--accent-color); text-decoration: none;">
                                            <strong>{{ $item['agent']->full_name }}</strong>
                                        </a>
                                    @else
                                        <span class="muted">{{ __('common.unknown') }}</span>
                                    @endif
                                </td>
                                <td style="padding: 12px; text-align: right;">
                                    {{ number_format($item['transaction_count']) }}
                                </td>
                                <td style="padding: 12px; text-align: right; color: var(--success-text); font-weight: bold;">
                                    {{ number_format($item['total_commission'], 2) }} {{ config('currency.default', 'CFA') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @else
        <div class="card" style="margin-top: 20px; padding: 40px; text-align: center;">
            <p class="muted">{{ __('common.no_transactions_this_month') }}</p>
        </div>
        @endif
    </div>
    @endif

    <div class="card" style="margin-top: 30px;">
        <h2 style="margin-bottom: 20px; color: var(--accent-color);">{{ __('common.users') }}</h2>
        @if($businessUnit->users->count() > 0)
            <div class="data-table">
                <table>
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border-color);">
                            <th>{{ __('common.name') }}</th>
                            <th>{{ __('common.email') }}</th>
                            <th>{{ __('common.role') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($businessUnit->users as $user)
                            @php
                                $pivot = $user->pivot;
                                $role = $pivot->role_id ? \App\Models\Role::find($pivot->role_id) : null;
                            @endphp
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $role ? $role->name : '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p style="color: var(--text-muted); text-align: center; padding: 20px;">{{ __('common.no_users') }}</p>
        @endif
    </div>
</div>
@else
<div class="card">
    <h1 style="margin-bottom: 20px; color: var(--accent-color);">{{ __('common.dashboard') }}</h1>
    <p class="muted">No business unit assigned. Please contact your administrator.</p>
</div>
@endif

@if(isset($monthlyData) && count($monthlyData) > 0)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('monthlyTransactionChart');
        if (!ctx) return;

        const monthlyData = @json($monthlyData);
        const monthLabels = monthlyData.map(item => item.month_name);
        const amounts = monthlyData.map(item => item.total_amount);
        
        // Get max value for Y-axis scaling
        const maxAmount = Math.max(...amounts, 1000); // Minimum scale of 1000
        const yAxisMax = Math.ceil(maxAmount / 250000) * 250000; // Round up to nearest 250k
        
        // Get CSS variables for theming
        const root = getComputedStyle(document.documentElement);
        const textPrimary = root.getPropertyValue('--text-primary').trim() || '#e0e0e0';
        const textSecondary = root.getPropertyValue('--text-secondary').trim() || '#aaa';
        const borderColor = root.getPropertyValue('--border-color').trim() || '#404040';
        const bgSecondary = root.getPropertyValue('--bg-secondary').trim() || '#2d2d2d';
        const accentColor = root.getPropertyValue('--accent-color').trim() || '#4a9eff';
        
        // Format Y-axis labels
        const formatCurrency = (value) => {
            if (value >= 1000000) {
                return (value / 1000000).toFixed(1) + 'M';
            } else if (value >= 1000) {
                return (value / 1000).toFixed(0) + 'k';
            }
            return value.toString();
        };

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [{
                    label: '{{ __('common.total_transaction_amount') }} ({{ config("currency.default", "CFA") }})',
                    data: amounts,
                    backgroundColor: 'rgba(74, 158, 255, 0.8)',
                    borderColor: 'rgba(74, 158, 255, 1)',
                    borderWidth: 1,
                    borderRadius: 4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'bottom',
                        labels: {
                            color: textPrimary,
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: bgSecondary,
                        titleColor: textPrimary,
                        bodyColor: textPrimary,
                        borderColor: borderColor,
                        borderWidth: 1,
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + 
                                    new Intl.NumberFormat('en-US', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }).format(context.parsed.y) + ' ' + 
                                    '{{ config("currency.default", "CFA") }}';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: yAxisMax,
                        ticks: {
                            color: textSecondary,
                            callback: function(value) {
                                return formatCurrency(value);
                            },
                            stepSize: 250000
                        },
                        grid: {
                            color: borderColor,
                            drawBorder: false
                        },
                        title: {
                            display: true,
                            text: '{{ __('common.values') }}',
                            color: textPrimary,
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    x: {
                        ticks: {
                            color: textSecondary,
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    });
</script>
@endpush
@endif
@endsection
