@extends('layouts.app')

@section('title', __('common.edit') . ' ' . __('common.business_unit'))
@section('page-title', __('common.edit') . ' ' . __('common.business_unit'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: #4a9eff;">{{ __('common.edit') }} {{ __('common.business_unit') }}</h1>

    @if(session('kato_credentials'))
        <div style="margin-bottom: 20px; padding: 15px; background: #2d4a2d; border: 1px solid #4a9eff; border-radius: 4px;">
            <strong style="color: #90ee90;">{{ __('common.kato_credentials_flash_title') }}</strong>
            <p style="margin: 10px 0 0; color: var(--text-primary); font-size: 14px;">
                <strong>{{ __('common.kato_admin_name') }}:</strong> {{ session('kato_credentials.adminName') ?? '-' }}<br>
                <strong>{{ __('common.admin_username') }}:</strong> {{ session('kato_credentials.adminUsername') ?? '-' }}<br>
                <strong>{{ __('common.admin_password') }}:</strong> {{ session('kato_credentials.adminPassword') ?? '-' }}
            </p>
            <p style="margin: 8px 0 0; color: var(--text-muted); font-size: 12px;">{{ __('common.kato_credentials_flash_note') }}</p>
        </div>
    @endif
    
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
                        <option value="{{ $cbs->id }}" data-code="{{ strtolower($cbs->code ?? '') }}" data-driver="{{ $cbs->driver ?? 'configurable' }}" {{ old('core_banking_system_id', $businessUnit->core_banking_system_id) == $cbs->id ? 'selected' : '' }}>
                            {{ $cbs->name }}@if($cbs->code) ({{ $cbs->code }})@endif
                        </option>
                    @endforeach
                </select>
                @error('core_banking_system_id')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            @php
                $isKato = $businessUnit->coreBankingSystem && $businessUnit->coreBankingSystem->isKatoDriver();
                $isConfigurableCbs = $businessUnit->coreBankingSystem && $businessUnit->coreBankingSystem->isConfigurableDriver();
                $cbsCredentials = $businessUnit->cbsCredentials;
                $katoRegisteredUsername = $businessUnit->getKatoRegisteredAdminUsername();
                $katoCanRefresh = $businessUnit->hasKatoRefreshCredentials();
                $katoCredentialsMismatch = $businessUnit->katoCredentialsMismatch();
            @endphp
            <div id="kato-admin-credentials" class="form-group" style="grid-column: 1 / -1; display: {{ $isKato ? 'block' : 'none' }}; padding: 15px; background: var(--bg-tertiary); border-radius: 4px; border: 1px solid var(--border-color);">
                <h3 style="color: #4a9eff; margin-bottom: 15px; font-size: 16px;">{{ __('common.kato_admin_credentials') }}</h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 15px;">{{ __('common.kato_admin_credentials_help') }}</p>

                @if($isKato && $businessUnit->kato_company_id)
                    @if($katoRegisteredUsername)
                        <p style="margin-bottom: 12px; color: var(--text-muted); font-size: 13px;">
                            <strong>{{ __('common.kato_registered_username') }}:</strong>
                            <code>{{ $katoRegisteredUsername }}</code>
                        </p>
                    @endif

                    @if(!$katoCanRefresh)
                        <div style="margin-bottom: 12px; padding: 12px; background: #5a5a2d; border-radius: 4px; color: #eeee90; font-size: 13px;">
                            {{ __('common.kato_credentials_missing_warning') }}
                        </div>
                    @elseif($katoCredentialsMismatch)
                        <div style="margin-bottom: 12px; padding: 12px; background: #5a3d2d; border-radius: 4px; color: #eecc90; font-size: 13px;">
                            {{ __('common.kato_credentials_mismatch_warning', ['username' => $katoRegisteredUsername]) }}
                        </div>
                    @endif

                    <div style="margin-bottom: 15px; padding: 12px; background: var(--bg-secondary); border-radius: 4px; font-size: 13px; color: var(--text-muted);">
                        <strong>{{ __('common.kato_password_recovery_title') }}</strong>
                        <p style="margin: 8px 0 0;">{{ __('common.kato_password_recovery_help') }}</p>
                    </div>
                @endif

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="kato_admin_name">{{ __('common.kato_admin_name') }}</label>
                        <input type="text" id="kato_admin_name" name="kato_admin_name" class="form-control"
                            value="{{ old('kato_admin_name', $businessUnit->kato_admin_name) }}">
                        @error('kato_admin_name')
                            <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="kato_admin_username">{{ __('common.admin_username') }}</label>
                        <input type="text" id="kato_admin_username" name="kato_admin_username" class="form-control"
                            value="{{ old('kato_admin_username', $businessUnit->kato_admin_username) }}">
                        @error('kato_admin_username')
                            <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="kato_admin_password">{{ __('common.admin_password') }}</label>
                        <input type="password" id="kato_admin_password" name="kato_admin_password" class="form-control"
                            placeholder="{{ __('common.kato_admin_password_leave_blank') }}" autocomplete="new-password">
                        @error('kato_admin_password')
                            <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                @if($isKato && $businessUnit->kato_company_id && $katoRegisteredUsername)
                    <div style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button type="submit"
                            formaction="{{ route('superadmin.business-units.restore-kato-username', $businessUnit->id) }}"
                            formmethod="post"
                            class="btn btn-secondary"
                            style="font-size: 13px;">
                            {{ __('common.kato_restore_username') }}
                        </button>
                    </div>
                @endif
            </div>

            <div id="configurable-cbs-credentials" class="form-group" style="grid-column: 1 / -1; display: {{ $isConfigurableCbs ? 'block' : 'none' }}; padding: 15px; background: var(--bg-tertiary); border-radius: 4px; border: 1px solid var(--border-color);">
                <h3 style="color: #4a9eff; margin-bottom: 15px; font-size: 16px;">{{ __('common.cbs_credentials') }}</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="cbs_username">{{ __('common.cbs_username') }}</label>
                        <input type="text" id="cbs_username" name="cbs_username" class="form-control" value="{{ old('cbs_username', $cbsCredentials->username ?? '') }}">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="cbs_password">{{ __('common.cbs_password') }}</label>
                        <input type="password" id="cbs_password" name="cbs_password" class="form-control" placeholder="{{ __('common.cbs_password_leave_blank') }}" autocomplete="new-password">
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="cbs_bearer_token">{{ __('common.cbs_bearer_token') }}</label>
                        <input type="password" id="cbs_bearer_token" name="cbs_bearer_token" class="form-control" placeholder="{{ __('common.cbs_password_leave_blank') }}" autocomplete="new-password">
                    </div>
                    <div class="form-group" style="margin: 0; grid-column: 1 / -1;">
                        <label class="form-label" for="cbs_extra_headers">{{ __('common.cbs_extra_headers') }}</label>
                        <textarea id="cbs_extra_headers" name="cbs_extra_headers" class="form-control" rows="3" placeholder='{"X-Company-ID": "..."}'>{{ old('cbs_extra_headers', isset($cbsCredentials) && $cbsCredentials?->extra_headers ? json_encode($cbsCredentials->extra_headers, JSON_PRETTY_PRINT) : '') }}</textarea>
                    </div>
                </div>
                @if($isConfigurableCbs)
                    <div style="margin-top: 15px;">
                        <a href="{{ route('superadmin.business-units.cbs-mappings.index', $businessUnit->id) }}" class="btn btn-secondary">{{ __('common.manage_cbs_mappings') }}</a>
                    </div>
                @endif
            </div>

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
                                    <strong>{{ __('common.company_name') }}:</strong> {{ $businessUnit->kato_api_response['data']['companyName'] ?? '-' }}<br>
                                    <strong>{{ __('common.sacco_type') }}:</strong> {{ $businessUnit->kato_api_response['data']['saccoType'] ?? '-' }}<br>
                                @endif
                                <strong>{{ __('common.kato_admin_user_id') }}:</strong> {{ $businessUnit->kato_admin_user_id ?? '-' }}<br>
                                <strong>{{ __('common.kato_token_status') }}:</strong>
                                @if($businessUnit->kato_token)
                                    @if($businessUnit->kato_token_expires_at && $businessUnit->kato_token_expires_at->isPast())
                                        {{ __('common.kato_token_expired') }}
                                    @else
                                        {{ __('common.kato_token_active') }}
                                    @endif
                                @else
                                    {{ __('common.kato_token_missing') }}
                                @endif
                                <br>
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
                            <button type="submit"
                                formaction="{{ route('superadmin.business-units.register-kato', $businessUnit->id) }}"
                                formmethod="post"
                                class="btn"
                                style="background: #4a9eff; margin-top: 10px;">
                                🔄 {{ __('common.sync_with_kato') }}
                            </button>
                        @else
                            <p style="margin-top: 10px; color: #ee9090; font-size: 13px;">
                                {{ __('common.kato_base_url_not_configured') }}
                            </p>
                        @endif
                    @else
                        <p style="margin-top: 10px; color: var(--text-muted); font-size: 13px;">
                            {{ __('common.kato_already_registered') }} - {{ __('common.company_id') }}: <strong>{{ $businessUnit->kato_company_id }}</strong>
                        </p>
                        <button type="submit"
                            formaction="{{ route('superadmin.business-units.refresh-kato-token', $businessUnit->id) }}"
                            formmethod="post"
                            class="btn btn-secondary"
                            style="margin-top: 10px;"
                            @disabled(! $katoCanRefresh)>
                            {{ __('common.kato_refresh_token') }}
                        </button>
                        @if(! $katoCanRefresh)
                            <p style="margin-top: 8px; color: #eecc90; font-size: 13px;">
                                {{ __('common.kato_refresh_token_disabled_help') }}
                            </p>
                        @endif
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

<script>
(function() {
    const cbsSelect = document.getElementById('core_banking_system_id');
    const katoSection = document.getElementById('kato-admin-credentials');
    const configurableSection = document.getElementById('configurable-cbs-credentials');
    if (!cbsSelect) return;

    function toggleCbsSections() {
        const option = cbsSelect.options[cbsSelect.selectedIndex];
        const driver = option ? option.getAttribute('data-driver') : '';
        const isKato = driver === 'kato';
        const isConfigurable = driver === 'configurable';

        if (katoSection) katoSection.style.display = isKato ? 'block' : 'none';
        if (configurableSection) configurableSection.style.display = isConfigurable ? 'block' : 'none';
    }

    cbsSelect.addEventListener('change', toggleCbsSections);
    toggleCbsSections();
})();
</script>
@endsection

