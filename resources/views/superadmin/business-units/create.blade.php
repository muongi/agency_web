@extends('layouts.app')

@section('title', __('common.create_business_unit'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent);">{{ __('common.create_business_unit') }}</h1>
    
    <form method="POST" action="{{ route('superadmin.business-units.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="form-group">
            <label class="form-label" for="name">{{ __('common.name') }} <span class="text-error">*</span></label>
            <input 
                type="text" 
                id="name" 
                name="name" 
                class="form-control" 
                value="{{ old('name') }}" 
                required 
                autofocus
            >
            @error('name')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="description">{{ __('common.description') }}</label>
            <textarea 
                id="description" 
                name="description" 
                class="form-control" 
                rows="4"
            >{{ old('description') }}</textarea>
            @error('description')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="email">{{ __('common.email') }}</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                class="form-control" 
                value="{{ old('email') }}"
            >
            @error('email')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="phone">{{ __('common.phone') }}</label>
            <input 
                type="text" 
                id="phone" 
                name="phone" 
                class="form-control" 
                value="{{ old('phone') }}"
            >
            @error('phone')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="form-group">
            <label class="form-label" for="address">{{ __('common.address') }}</label>
            <textarea 
                id="address" 
                name="address" 
                class="form-control" 
                rows="3"
            >{{ old('address') }}</textarea>
            @error('address')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
            <div class="form-group">
                <label class="form-label" for="account">{{ __('common.account') }}</label>
                <input 
                    type="text" 
                    id="account" 
                    name="account" 
                    class="form-control" 
                    value="{{ old('account') }}"
                >
                @error('account')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="tax_number">{{ __('common.tax_number') }}</label>
                <input 
                    type="text" 
                    id="tax_number" 
                    name="tax_number" 
                    class="form-control" 
                    value="{{ old('tax_number') }}"
                >
                @error('tax_number')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <label class="form-label" for="mailing_address">{{ __('common.mailing_address') }}</label>
                <textarea 
                    id="mailing_address" 
                    name="mailing_address" 
                    class="form-control" 
                    rows="3"
                >{{ old('mailing_address') }}</textarea>
                @error('mailing_address')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="relation_manager_phone">{{ __('common.relation_manager_phone') }}</label>
                <input 
                    type="text" 
                    id="relation_manager_phone" 
                    name="relation_manager_phone" 
                    class="form-control" 
                    value="{{ old('relation_manager_phone') }}"
                >
                @error('relation_manager_phone')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="relation_manager_email">{{ __('common.relation_manager_email') }}</label>
                <input 
                    type="email" 
                    id="relation_manager_email" 
                    name="relation_manager_email" 
                    class="form-control" 
                    value="{{ old('relation_manager_email') }}"
                >
                @error('relation_manager_email')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="sms_sender_number">{{ __('common.sms_sender_number') }}</label>
                <input 
                    type="text" 
                    id="sms_sender_number" 
                    name="sms_sender_number" 
                    class="form-control" 
                    value="{{ old('sms_sender_number') }}"
                    placeholder="tel:+22676396790"
                >
                <small style="color: var(--text-muted); font-size: 12px; margin-top: 5px; display: block;">{{ __('common.sms_sender_number_format') }}</small>
                @error('sms_sender_number')
                    <div class="field-error">{{ $message }}</div>
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
                        <option value="{{ $cbs->id }}" data-code="{{ strtolower($cbs->code ?? '') }}" {{ old('core_banking_system_id') == $cbs->id ? 'selected' : '' }}>
                            {{ $cbs->name }}@if($cbs->code) ({{ $cbs->code }})@endif
                        </option>
                    @endforeach
                </select>
                @error('core_banking_system_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <label class="form-label" for="logo">{{ __('common.logo') }}</label>
                <input 
                    type="file" 
                    id="logo" 
                    name="logo" 
                    class="form-control" 
                    accept="image/*"
                >
                <small style="color: var(--text-muted); font-size: 12px; margin-top: 5px; display: block;">{{ __('common.logo_help') }}</small>
                @error('logo')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div id="kato-admin-credentials" class="form-group" style="grid-column: 1 / -1; display: none; padding: 15px; background: var(--bg-tertiary); border-radius: 4px; border: 1px solid var(--border-color);">
                <h3 style="color: var(--accent); margin-bottom: 10px; font-size: 16px;">{{ __('common.kato_admin_credentials') }}</h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-bottom: 15px;">{{ __('common.kato_admin_credentials_optional') }}</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px;">
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="kato_admin_name">{{ __('common.kato_admin_name') }}</label>
                        <input type="text" id="kato_admin_name" name="kato_admin_name" class="form-control" value="{{ old('kato_admin_name') }}">
                        @error('kato_admin_name')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="kato_admin_username">{{ __('common.admin_username') }}</label>
                        <input type="text" id="kato_admin_username" name="kato_admin_username" class="form-control" value="{{ old('kato_admin_username') }}">
                        @error('kato_admin_username')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="form-group" style="margin: 0;">
                        <label class="form-label" for="kato_admin_password">{{ __('common.admin_password') }}</label>
                        <input type="password" id="kato_admin_password" name="kato_admin_password" class="form-control" autocomplete="new-password">
                        @error('kato_admin_password')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        @endif

        <div class="form-group" style="margin-top: 30px; padding: 20px; background: var(--bg-secondary); border-radius: 4px; border: 1px solid var(--border-color);">
            <label class="form-label" style="margin-bottom: 15px; color: var(--accent); font-size: 16px;">{{ __('common.modules') }}</label>
            <div class="checkbox-group">
                <input type="checkbox" id="cnss_enabled" name="cnss_enabled" value="1" {{ old('cnss_enabled') ? 'checked' : '' }}>
                <label for="cnss_enabled">{{ __('common.cnss_module_enabled') }}</label>
            </div>
            <small class="text-muted-sm" style="display: block; margin-top: 8px;">{{ __('common.cnss_module_enabled_help') }}</small>
            @error('cnss_enabled')
                <div class="field-error">{{ $message }}</div>
            @enderror
        </div>

        @if(isset($globalTransactionTypes) && $globalTransactionTypes->count() > 0)
        <div class="form-group" style="margin-top: 30px; padding: 20px; background: var(--bg-secondary); border-radius: 4px; border: 1px solid var(--border-color);">
            <label class="form-label" style="margin-bottom: 15px; color: var(--accent); font-size: 16px;">{{ __('common.assign_transaction_types') }}</label>
            <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 15px;">{{ __('common.select_transaction_types_for_business') }}</p>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;">
                @foreach($globalTransactionTypes as $transactionType)
                    <div style="padding: 12px; background: var(--bg-tertiary); border-radius: 4px; border: 1px solid var(--border-color);">
                        <label style="display: flex; align-items: start; cursor: pointer;">
                            <input 
                                type="checkbox" 
                                name="transaction_types[]" 
                                value="{{ $transactionType->id }}"
                                {{ in_array($transactionType->id, old('transaction_types', [])) ? 'checked' : '' }}
                                style="margin-right: 10px; margin-top: 3px;"
                            >
                            <div style="flex: 1;">
                                <div style="font-weight: bold; color: var(--accent); margin-bottom: 5px;">{{ $transactionType->name }}</div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 5px;">
                                    <code style="background: var(--bg-primary); padding: 2px 6px; border-radius: 3px;">{{ $transactionType->code }}</code>
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
                <div style="color: var(--error-text); margin-top: 10px; font-size: 14px;">{{ $message }}</div>
            @enderror
            @error('transaction_types.*')
                <div style="color: var(--error-text); margin-top: 10px; font-size: 14px;">{{ $message }}</div>
            @enderror
        </div>
        @else
        <div class="form-group" style="margin-top: 30px; padding: 20px; background: var(--bg-secondary); border-radius: 4px; border: 1px solid var(--border-color);">
            <p class="muted">{{ __('common.no_global_transaction_types') }}</p>
            <a href="{{ route('superadmin.transaction-types.create') }}" class="btn" style="margin-top: 10px;">{{ __('common.create_transaction_type') }}</a>
        </div>
        @endif

        <div class="btn-row mt-24">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('superadmin.dashboard') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>

<script>
(function() {
    const cbsSelect = document.getElementById('core_banking_system_id');
    const katoSection = document.getElementById('kato-admin-credentials');
    if (!cbsSelect || !katoSection) return;

    function toggleKatoCredentials() {
        const option = cbsSelect.options[cbsSelect.selectedIndex];
        const isKato = option && option.getAttribute('data-code') === 'kato';
        katoSection.style.display = isKato ? 'block' : 'none';
    }

    cbsSelect.addEventListener('change', toggleKatoCredentials);
    toggleKatoCredentials();
})();
</script>
@endsection

