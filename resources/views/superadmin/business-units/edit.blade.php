@extends('layouts.app')

@section('title', __('common.edit') . ' ' . __('common.business_unit'))
@section('page-title', __('common.edit') . ' ' . __('common.business_unit'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: #4a9eff;">{{ __('common.edit') }} {{ __('common.business_unit') }}</h1>
    
    <form method="POST" action="{{ route('superadmin.business-units.update', $businessUnit->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-group">
            <label class="form-label" for="name">{{ __('common.name') }} <span style="color: #ff6b6b;">*</span></label>
            <input 
                type="text" 
                id="name" 
                name="name" 
                class="form-control" 
                value="{{ old('name', $businessUnit->name) }}" 
                required 
                autofocus
            >
            @error('name')
                <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="description">{{ __('common.description') }}</label>
            <textarea 
                id="description" 
                name="description" 
                class="form-control" 
                rows="4"
            >{{ old('description', $businessUnit->description) }}</textarea>
            @error('description')
                <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
            @enderror
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="email">{{ __('common.email') }}</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    value="{{ old('email', $businessUnit->email) }}"
                >
                @error('email')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">{{ __('common.phone') }}</label>
                <input 
                    type="text" 
                    id="phone" 
                    name="phone" 
                    class="form-control" 
                    value="{{ old('phone', $businessUnit->phone) }}"
                >
                @error('phone')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="address">{{ __('common.address') }}</label>
                <textarea 
                    id="address" 
                    name="address" 
                    class="form-control" 
                    rows="3"
                >{{ old('address', $businessUnit->address) }}</textarea>
                @error('address')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="account">{{ __('common.account') }}</label>
                <input 
                    type="text" 
                    id="account" 
                    name="account" 
                    class="form-control" 
                    value="{{ old('account', $businessUnit->account) }}"
                >
                @error('account')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="tax_number">{{ __('common.tax_number') }}</label>
                <input 
                    type="text" 
                    id="tax_number" 
                    name="tax_number" 
                    class="form-control" 
                    value="{{ old('tax_number', $businessUnit->tax_number) }}"
                >
                @error('tax_number')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="mailing_address">{{ __('common.mailing_address') }}</label>
                <textarea 
                    id="mailing_address" 
                    name="mailing_address" 
                    class="form-control" 
                    rows="3"
                >{{ old('mailing_address', $businessUnit->mailing_address) }}</textarea>
                @error('mailing_address')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="relation_manager_phone">{{ __('common.relation_manager_phone') }}</label>
                <input 
                    type="text" 
                    id="relation_manager_phone" 
                    name="relation_manager_phone" 
                    class="form-control" 
                    value="{{ old('relation_manager_phone', $businessUnit->relation_manager_phone) }}"
                >
                @error('relation_manager_phone')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="relation_manager_email">{{ __('common.relation_manager_email') }}</label>
                <input 
                    type="email" 
                    id="relation_manager_email" 
                    name="relation_manager_email" 
                    class="form-control" 
                    value="{{ old('relation_manager_email', $businessUnit->relation_manager_email) }}"
                >
                @error('relation_manager_email')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="sms_sender_number">{{ __('common.sms_sender_number') }}</label>
                <input 
                    type="text" 
                    id="sms_sender_number" 
                    name="sms_sender_number" 
                    class="form-control" 
                    value="{{ old('sms_sender_number', $businessUnit->sms_sender_number) }}"
                    placeholder="tel:+22676396790"
                >
                <small style="color: var(--text-muted); font-size: 12px; margin-top: 5px; display: block;">{{ __('common.sms_sender_number_format') }}</small>
                @error('sms_sender_number')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="core_banking_system_id">{{ __('common.select_cbs') }}</label>
                <select 
                    id="core_banking_system_id" 
                    name="core_banking_system_id" 
                    class="form-control"
                >
                    <option value="">{{ __('common.no_cbs_selected') }}</option>
                    @foreach($coreBankingSystems as $cbs)
                        <option value="{{ $cbs->id }}" {{ old('core_banking_system_id', $businessUnit->core_banking_system_id) == $cbs->id ? 'selected' : '' }}>
                            {{ $cbs->name }}@if($cbs->code) ({{ $cbs->code }})@endif
                        </option>
                    @endforeach
                </select>
                @error('core_banking_system_id')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            @php
                $isKato = $businessUnit->coreBankingSystem && strtolower($businessUnit->coreBankingSystem->code) === 'kato';
            @endphp
            @if($isKato)
                <div class="form-group" style="grid-column: 1 / -1; padding: 15px; background: var(--bg-secondary); border-radius: 4px; border: 1px solid var(--border-color);">
                    <h3 style="color: #4a9eff; margin-bottom: 15px; font-size: 16px;">{{ __('common.kato_sync_status') }}</h3>
                    
                    @if($businessUnit->kato_company_id)
                        <div style="margin-bottom: 10px;">
                            <span style="padding: 6px 12px; border-radius: 4px; font-size: 12px; background: #2d5a2d; color: #90ee90; display: inline-block;">
                                ✓ {{ __('common.kato_registered') }}
                            </span>
                            <p style="margin-top: 8px; color: var(--text-muted); font-size: 13px;">
                                <strong>{{ __('common.kato_company_id') }}:</strong> {{ $businessUnit->kato_company_id }}<br>
                                @if($businessUnit->kato_api_response)
                                    <strong>{{ __('common.company_name') }}:</strong> {{ $businessUnit->kato_api_response['companyName'] ?? '-' }}<br>
                                    <strong>{{ __('common.sacco_type') }}:</strong> {{ $businessUnit->kato_api_response['saccoType'] ?? '-' }}<br>
                                    <strong>{{ __('common.admin_username') }}:</strong> {{ $businessUnit->kato_api_response['adminUsername'] ?? '-' }}<br>
                                @endif
                                <strong>{{ __('common.kato_admin_user_id') }}:</strong> {{ $businessUnit->kato_admin_user_id ?? '-' }}<br>
                                <strong>{{ __('common.registered_at') }}:</strong> 
                                @if($businessUnit->kato_registered_at)
                                    @php
                                        $registeredAt = is_string($businessUnit->kato_registered_at) 
                                            ? \Carbon\Carbon::parse($businessUnit->kato_registered_at) 
                                            : $businessUnit->kato_registered_at;
                                    @endphp
                                    {{ $registeredAt->format('Y-m-d H:i:s') }}
                                @else
                                    -
                                @endif
                                <br>
                            </p>
                        </div>
                    @elseif($businessUnit->kato_registration_error)
                        <div style="margin-bottom: 10px;">
                            <span style="padding: 6px 12px; border-radius: 4px; font-size: 12px; background: #5a2d2d; color: #ee9090; display: inline-block;">
                                ✗ {{ __('common.kato_registration_failed') }}
                            </span>
                            <p style="margin-top: 8px; color: #ee9090; font-size: 13px;">
                                <strong>{{ __('common.error') }}:</strong> {{ $businessUnit->kato_registration_error }}
                            </p>
                        </div>
                    @else
                        <div style="margin-bottom: 10px;">
                            <span style="padding: 6px 12px; border-radius: 4px; font-size: 12px; background: #5a5a2d; color: #eeee90; display: inline-block;">
                                ⏳ {{ __('common.kato_registration_pending') }}
                            </span>
                        </div>
                    @endif

                    @if(!$businessUnit->kato_company_id)
                        @if($businessUnit->coreBankingSystem->base_url)
                            <form action="{{ route('superadmin.business-units.register-kato', $businessUnit->id) }}" method="POST" style="display: inline-block; margin-top: 10px;">
                                @csrf
                                <button type="submit" class="btn" style="background: #4a9eff;">
                                    🔄 {{ __('common.sync_with_kato') }}
                                </button>
                            </form>
                        @else
                            <p style="margin-top: 10px; color: #ee9090; font-size: 13px;">
                                {{ __('common.kato_base_url_not_configured') }}
                            </p>
                        @endif
                    @else
                        <p style="margin-top: 10px; color: var(--text-muted); font-size: 13px;">
                            {{ __('common.kato_already_registered') }} - {{ __('common.company_id') }}: <strong>{{ $businessUnit->kato_company_id }}</strong>
                        </p>
                    @endif
                </div>
            @endif

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="logo">{{ __('common.logo') }}</label>
                @if($businessUnit->logo)
                    <div style="margin-bottom: 10px;">
                        <img src="{{ asset('storage/' . $businessUnit->logo) }}" alt="{{ $businessUnit->name }}" style="max-width: 200px; max-height: 100px; border-radius: 4px; border: 1px solid #404040;">
                        <p style="color: #888; font-size: 12px; margin-top: 5px;">{{ __('common.current_logo') }}</p>
                    </div>
                @endif
                <input 
                    type="file" 
                    id="logo" 
                    name="logo" 
                    class="form-control" 
                    accept="image/*"
                >
                <small style="color: #888; font-size: 12px; margin-top: 5px; display: block;">{{ __('common.logo_help') }}</small>
                @error('logo')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        @if(isset($globalTransactionTypes) && $globalTransactionTypes->count() > 0)
        <div class="form-group" style="margin-top: 30px; padding: 20px; background: #2d2d2d; border-radius: 4px; border: 1px solid #404040;">
            <label class="form-label" style="margin-bottom: 15px; color: #4a9eff; font-size: 16px;">{{ __('common.assign_transaction_types') }}</label>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 15px;">{{ __('common.select_transaction_types_for_business') }}</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                @foreach($globalTransactionTypes as $transactionType)
                    <div style="padding: 12px; background: #3d3d3d; border-radius: 4px; border: 1px solid {{ in_array($transactionType->id, old('transaction_types', $assignedTransactionTypeIds ?? [])) ? '#4a9eff' : '#404040' }};">
                        <label style="display: flex; align-items: start; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                name="transaction_types[]" 
                                value="{{ $transactionType->id }}"
                                {{ in_array($transactionType->id, old('transaction_types', $assignedTransactionTypeIds ?? [])) ? 'checked' : '' }}
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
            @error('transaction_types')
                <div style="color: #ff6b6b; margin-top: 10px; font-size: 14px;">{{ $message }}</div>
            @enderror
            @error('transaction_types.*')
                <div style="color: #ff6b6b; margin-top: 10px; font-size: 14px;">{{ $message }}</div>
            @enderror
        </div>
        @else
        <div class="form-group" style="margin-top: 30px; padding: 20px; background: #2d2d2d; border-radius: 4px; border: 1px solid #404040;">
            <p style="color: var(--text-muted);">{{ __('common.no_global_transaction_types') }}</p>
            <a href="{{ route('superadmin.transaction-types.create') }}" class="btn" style="margin-top: 10px;">{{ __('common.create_transaction_type') }}</a>
        </div>
        @endif

        <div style="display: flex; gap: 10px; margin-top: 24px;">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('superadmin.business-units.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

