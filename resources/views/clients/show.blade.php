@extends('layouts.app')

@section('title', __('common.view_client'))
@section('page-title', __('common.view_client'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: var(--accent-color);">{{ __('common.client_details') }}</h1>
        <div style="display: flex; gap: 10px;">
            @php
                $user = auth()->user();
                $businessUnit = $user->currentBusinessUnit;
                $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-client', $businessUnit->id));
            @endphp
            @if($canEdit)
                <a href="{{ route('clients.edit', $client->id) }}" class="btn">{{ __('common.edit') }}</a>
            @endif
            <a href="{{ route('clients.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
        </div>
    </div>

    <!-- Client Header with Photo -->
    <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 30px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        @if($client->photo)
            <img src="{{ asset('storage/' . $client->photo) }}" alt="{{ $client->full_name }}" style="width: 120px; height: 120px; border-radius: 8px; object-fit: cover; border: 2px solid var(--border-color);">
        @else
            <div style="width: 120px; height: 120px; border-radius: 8px; background: var(--bg-primary); border: 2px solid var(--border-color); display: flex; align-items: center; justify-content: center; font-size: 48px; color: var(--text-muted);">
                👤
            </div>
        @endif
        <div>
            <h2 style="color: var(--accent-color); margin-bottom: 5px;">{{ $client->full_name }}</h2>
            @if($client->email)
                <p style="color: var(--text-secondary); margin: 5px 0;">{{ __('common.email') }}: {{ $client->email }}</p>
            @endif
            @if($client->phone_number)
                <p style="color: var(--text-secondary); margin: 5px 0;">{{ __('common.phone_number') }}: {{ $client->phone_number }}</p>
            @endif
            @if($client->id_passport)
                <p style="color: var(--text-secondary); margin: 5px 0;">{{ __('common.id_passport') }}: {{ $client->id_passport }}</p>
            @endif
        </div>
    </div>

    <!-- Tabs Navigation -->
    <div style="border-bottom: 2px solid var(--border-color); margin-bottom: 30px;">
        <div style="display: flex; gap: 0;">
            <a href="{{ route('clients.show', ['client' => $client->id, 'tab' => 'information']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'information' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.information') }}
            </a>
            <a href="{{ route('clients.show', ['client' => $client->id, 'tab' => 'accounts']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'accounts' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.accounts') }} ({{ $client->accounts->count() }})
            </a>
            <a href="{{ route('clients.show', ['client' => $client->id, 'tab' => 'transactions']) }}" 
               class="tab-link {{ ($activeTab ?? 'information') == 'transactions' ? 'active' : '' }}" 
               style="padding: 12px 24px; text-decoration: none; color: var(--text-secondary); border-bottom: 3px solid transparent; transition: all 0.3s; font-weight: 500;">
                {{ __('common.transaction_history') }}
            </a>
        </div>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        @if(($activeTab ?? 'information') == 'information')
            <!-- Information Tab -->
            <div>
                <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.personal_information') }}</h2>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.name') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->full_name }}</p>
                    </div>
                    
                    @if($client->salutation)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.salutation') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->salutation }}</p>
                    </div>
                    @endif

                    @if($client->email)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.email') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->email }}</p>
                    </div>
                    @endif

                    @if($client->phone_number)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.phone_number') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->phone_number }}</p>
                    </div>
                    @endif

                    @if($client->id_passport)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.id_passport') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->id_passport }}</p>
                    </div>
                    @endif

                    @if($client->home_address)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.home_address') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->home_address }}</p>
                    </div>
                    @endif

                    @if($client->gender)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.gender') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->gender }}</p>
                    </div>
                    @endif

                    @if($client->marital_status)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.marital_status') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->marital_status }}</p>
                    </div>
                    @endif

                    @if($client->year_of_birth)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.year_of_birth') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->year_of_birth }}</p>
                    </div>
                    @endif

                    @if($client->region)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.region') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->region }}</p>
                    </div>
                    @endif

                    @if($client->home_town)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.home_town') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->home_town }}</p>
                    </div>
                    @endif

                    @if($client->nationality)
                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.nationality') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->nationality }}</p>
                    </div>
                    @endif

                    <div>
                        <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.biometrics_status') }}:</strong>
                        <p style="color: var(--text-primary); margin: 0 0 15px 0;">
                            <span style="text-transform: capitalize; padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                @if($client->biometrics_status) background: var(--success-bg); color: var(--success-text);
                                @else background: var(--bg-tertiary); color: var(--text-muted);
                                @endif">
                                {{ $client->biometrics_status ? __('common.active') : __('common.inactive') }}
                            </span>
                        </p>
                    </div>
                </div>

                <!-- Next of Kin Information -->
                @if($client->next_of_kin_first_name || $client->next_of_kin_last_name)
                <div style="margin-top: 30px;">
                    <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.next_of_kin_information') }}</h2>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                        @if($client->next_of_kin_full_name)
                        <div>
                            <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.name') }}:</strong>
                            <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->next_of_kin_full_name }}</p>
                        </div>
                        @endif

                        @if($client->next_of_kin_phone_number)
                        <div>
                            <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.phone_number') }}:</strong>
                            <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->next_of_kin_phone_number }}</p>
                        </div>
                        @endif

                        @if($client->next_of_kin_address)
                        <div>
                            <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.address') }}:</strong>
                            <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->next_of_kin_address }}</p>
                        </div>
                        @endif

                        @if($client->next_of_kin_gender)
                        <div>
                            <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.gender') }}:</strong>
                            <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $client->next_of_kin_gender }}</p>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>

        @elseif(($activeTab ?? 'information') == 'accounts')
            <!-- Accounts Tab -->
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2 style="color: var(--accent-color);">{{ __('common.accounts') }} ({{ $client->accounts->count() }})</h2>
                    <a href="{{ route('accounts.create', ['accountable_type' => 'client', 'accountable_id' => $client->id]) }}" class="btn">+ {{ __('common.add_account') }}</a>
                </div>

                @if($client->accounts->count() > 0)
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color);">
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.account_number') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.account_type') }}</th>
                                    <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.balance') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.currency') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.status') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.opening_date') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($client->accounts as $account)
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 12px;"><code style="background: var(--bg-tertiary); padding: 4px 8px; border-radius: 4px;">{{ $account->account_number }}</code></td>
                                        <td style="padding: 12px; text-transform: capitalize;">{{ $account->account_type }}</td>
                                        <td style="padding: 12px; text-align: right; font-weight: bold;">{{ number_format($account->balance, 2) }}</td>
                                        <td style="padding: 12px;">{{ $account->currency }}</td>
                                        <td style="padding: 12px;">
                                            <span style="text-transform: capitalize; padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                                                @if($account->status === 'active') background: var(--success-bg); color: var(--success-text);
                                                @elseif($account->status === 'inactive') background: var(--bg-tertiary); color: var(--text-muted);
                                                @elseif($account->status === 'suspended') background: #ff9800; color: white;
                                                @else background: var(--error-bg); color: var(--error-text);
                                                @endif">
                                                {{ $account->status }}
                                            </span>
                                        </td>
                                        <td style="padding: 12px;">{{ $account->opening_date->format('Y-m-d') }}</td>
                                        <td style="padding: 12px;">
                                            <div style="display: flex; gap: 5px;">
                                                <a href="{{ route('accounts.show', $account->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.view') }}</a>
                                                <a href="{{ route('accounts.edit', $account->id) }}" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div style="margin-top: 15px; padding: 15px; background: var(--bg-tertiary); border-radius: 4px;">
                        <strong style="color: var(--accent-color);">{{ __('common.total_balance') }}:</strong>
                        <span style="color: var(--text-primary); font-size: 18px; font-weight: bold; margin-left: 10px;">
                            {{ number_format($client->total_account_balance, 2) }} {{ $client->accounts->first()->currency ?? config('currency.default', 'CFA') }}
                        </span>
                    </div>
                @else
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <p style="margin-bottom: 20px;">{{ __('common.no_accounts') }}</p>
                        <a href="{{ route('accounts.create', ['accountable_type' => 'client', 'accountable_id' => $client->id]) }}" class="btn">{{ __('common.add_account') }}</a>
                    </div>
                @endif
            </div>

        @elseif(($activeTab ?? 'information') == 'transactions')
            <!-- Transaction History Tab -->
            <div>
                <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.transaction_history') }}</h2>

                <!-- Filters -->
                <form method="GET" action="{{ route('clients.show', ['client' => $client->id, 'tab' => 'transactions']) }}" style="margin-bottom: 20px; padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                        <div class="form-group">
                            <label class="form-label">{{ __('common.start_date') }}</label>
                            <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ __('common.end_date') }}</label>
                            <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">{{ __('common.status') }}</label>
                            <select name="status" class="form-select">
                                <option value="">{{ __('common.all') }}</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>{{ __('common.pending') }}</option>
                                <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>{{ __('common.completed') }}</option>
                                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>{{ __('common.failed') }}</option>
                                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>{{ __('common.cancelled') }}</option>
                            </select>
                        </div>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn">{{ __('common.filter') }}</button>
                        <a href="{{ route('clients.show', ['client' => $client->id, 'tab' => 'transactions']) }}" class="btn btn-secondary">{{ __('common.clear') }}</a>
                    </div>
                </form>

                @if($transactions->count() > 0)
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--border-color);">
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.transaction_date') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.transaction_type') }}</th>
                                    <th style="padding: 12px; text-align: right; color: var(--accent-color);">{{ __('common.transaction_amount') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.agent') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.terminal_serial') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.status') }}</th>
                                    <th style="padding: 12px; text-align: left; color: var(--accent-color);">{{ __('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $transaction)
                                    <tr style="border-bottom: 1px solid var(--border-color);">
                                        <td style="padding: 12px;">
                                            {{ $transaction->transaction_date->format('Y-m-d H:i') }}
                                        </td>
                                        <td style="padding: 12px;">
                                            <strong>{{ $transaction->transactionType->name }}</strong>
                                            <br>
                                            <code style="background: var(--bg-tertiary); padding: 2px 6px; border-radius: 4px; font-size: 11px;">{{ $transaction->transactionType->code }}</code>
                                        </td>
                                        <td style="padding: 12px; text-align: right; font-weight: bold;">
                                            {{ number_format($transaction->transaction_amount, 2) }}
                                        </td>
                                        <td style="padding: 12px;">
                                            {{ $transaction->agent ? $transaction->agent->full_name : '-' }}
                                        </td>
                                        <td style="padding: 12px;">
                                            {{ $transaction->terminal ? $transaction->terminal->serial_number : '-' }}
                                        </td>
                                        <td style="padding: 12px;">
                                            @if($transaction->status === 'completed')
                                                <span style="color: var(--success-text);">{{ __('common.completed') }}</span>
                                            @elseif($transaction->status === 'pending')
                                                <span style="color: #ffa500;">{{ __('common.pending') }}</span>
                                            @elseif($transaction->status === 'failed')
                                                <span style="color: var(--error-text);">{{ __('common.failed') }}</span>
                                            @else
                                                <span style="color: var(--text-muted);">{{ __('common.cancelled') }}</span>
                                            @endif
                                        </td>
                                        <td style="padding: 12px;">
                                            <a href="{{ route('transactions.show', $transaction->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.view') }}</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 20px;">
                        {{ $transactions->links() }}
                    </div>
                @else
                    <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                        <p style="margin-bottom: 20px;">{{ __('common.no_transactions') }}</p>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>

@push('styles')
<style>
    .tab-link {
        position: relative;
    }
    
    .tab-link:hover {
        color: var(--accent-color);
        background: var(--bg-tertiary);
    }
    
    .tab-link.active {
        color: var(--accent-color);
        border-bottom-color: var(--accent-color) !important;
    }
</style>
@endpush
@endsection
