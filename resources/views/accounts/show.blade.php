@extends('layouts.app')

@section('title', __('common.view_account'))
@section('page-title', __('common.view_account'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.account_details') }}</h1>
        <div class="btn-row">
            @php
                $user = auth()->user();
                $businessUnit = $user->currentBusinessUnit;
                $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-account', $businessUnit->id));
            @endphp
            @if($canEdit)
                <a href="{{ route('accounts.edit', $account->id) }}" class="btn">{{ __('common.edit') }}</a>
            @endif
            <a href="{{ route('accounts.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 30px;">
        <!-- Account Information -->
        <div>
            <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.account_information') }}</h2>
            
            <div style="display: grid; gap: 15px;">
                <div>
                    <strong class="text-accent">{{ __('common.account_number') }}:</strong>
                    <p style="color: var(--text-primary); margin-top: 5px;"><code style="background: var(--bg-tertiary); padding: 8px 12px; border-radius: 4px; font-size: 16px;">{{ $account->account_number }}</code></p>
                </div>

                <div>
                    <strong class="text-accent">{{ __('common.account_type') }}:</strong>
                    <p style="color: var(--text-primary); margin-top: 5px; text-transform: capitalize;">{{ $account->account_type }}</p>
                </div>

                <div>
                    <strong class="text-accent">{{ __('common.balance') }}:</strong>
                    <p style="color: var(--text-primary); margin-top: 5px; font-size: 24px; font-weight: bold;">{{ number_format($account->balance, 2) }} {{ $account->currency }}</p>
                </div>

                <div>
                    <strong class="text-accent">{{ __('common.currency') }}:</strong>
                    <p style="color: var(--text-primary); margin-top: 5px;">{{ $account->currency }}</p>
                </div>

                <div>
                    <strong class="text-accent">{{ __('common.status') }}:</strong>
                    <p style="color: var(--text-primary); margin-top: 5px;">
                        <span style="text-transform: capitalize; padding: 6px 12px; border-radius: 4px; font-size: 14px; 
                            @if($account->status === 'active') background: var(--success-bg); color: var(--success-text);
                            @elseif($account->status === 'inactive') background: var(--bg-tertiary); color: var(--text-muted);
                            @elseif($account->status === 'suspended') background: var(--warning-bg); color: var(--warning-text);
                            @else background: var(--error-bg); color: var(--error-text);
                            @endif">
                            {{ $account->status }}
                        </span>
                    </p>
                </div>

                <div>
                    <strong class="text-accent">{{ __('common.opening_date') }}:</strong>
                    <p style="color: var(--text-primary); margin-top: 5px;">{{ $account->opening_date->format('Y-m-d') }}</p>
                </div>

                @if($account->closing_date)
                <div>
                    <strong class="text-accent">{{ __('common.closing_date') }}:</strong>
                    <p style="color: var(--text-primary); margin-top: 5px;">{{ $account->closing_date->format('Y-m-d') }}</p>
                </div>
                @endif

                @if($account->description)
                <div>
                    <strong class="text-accent">{{ __('common.description') }}:</strong>
                    <p style="color: var(--text-primary); margin-top: 5px;">{{ $account->description }}</p>
                </div>
                @endif

                @if($account->isRequired())
                <div style="padding: 10px; background: var(--bg-tertiary); border-radius: 4px; border-left: 3px solid var(--accent-color);">
                    <small class="muted">{{ __('common.required_account_note') }}</small>
                    <br>
                    <small style="color: var(--text-muted); margin-top: 5px; display: block;">{{ __('common.account_number_cbs_note') }}</small>
                </div>
                @endif
            </div>
        </div>

        <!-- Owner Information -->
        <div>
            <h2 style="color: var(--accent-color); margin-bottom: 20px;">
                @if($account->isForClient())
                    {{ __('common.client_information') }}
                @else
                    {{ __('common.agent_information') }}
                @endif
            </h2>
            
            <div style="display: grid; gap: 15px;">
                <div>
                    <strong class="text-accent">{{ __('common.name') }}:</strong>
                    <p style="color: var(--text-primary); margin-top: 5px;">{{ $account->accountable->full_name ?? 'N/A' }}</p>
                </div>

                @if($account->isForClient())
                    @if($account->accountable->email)
                    <div>
                        <strong class="text-accent">{{ __('common.email') }}:</strong>
                        <p style="color: var(--text-primary); margin-top: 5px;">{{ $account->accountable->email }}</p>
                    </div>
                    @endif

                    @if($account->accountable->phone_number)
                    <div>
                        <strong class="text-accent">{{ __('common.phone_number') }}:</strong>
                        <p style="color: var(--text-primary); margin-top: 5px;">{{ $account->accountable->phone_number }}</p>
                    </div>
                    @endif
                @else
                    <div>
                        <strong class="text-accent">{{ __('common.username') }}:</strong>
                        <p style="color: var(--text-primary); margin-top: 5px;">{{ $account->accountable->username ?? 'N/A' }}</p>
                    </div>

                    <div>
                        <strong class="text-accent">{{ __('common.email') }}:</strong>
                        <p style="color: var(--text-primary); margin-top: 5px;">{{ $account->accountable->email ?? 'N/A' }}</p>
                    </div>

                    @if($account->accountable->phone_number)
                    <div>
                        <strong class="text-accent">{{ __('common.phone_number') }}:</strong>
                        <p style="color: var(--text-primary); margin-top: 5px;">{{ $account->accountable->phone_number }}</p>
                    </div>
                    @endif
                @endif

                <div style="margin-top: 15px;">
                    @if($account->isForClient())
                        <a href="{{ route('clients.show', $account->accountable_id) }}" class="btn btn-sm">{{ __('common.view_client') }}</a>
                    @else
                        <a href="{{ route('agents.show', $account->accountable_id) }}" class="btn btn-sm">{{ __('common.view_agent') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

