@extends('layouts.app')

@section('title', __('common.add_account'))
@section('page-title', __('common.add_account'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.add_account') }}</h1>
    
    <form method="POST" action="{{ route('accounts.store') }}">
        @csrf

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="accountable_type">{{ __('common.account_owner_type') }} <span class="text-error">*</span></label>
                <select id="accountable_type" name="accountable_type" class="form-select" required onchange="toggleAccountableSelect()">
                    <option value="">{{ __('common.select_owner_type') }}</option>
                    <option value="client" {{ old('accountable_type', $accountable ? ($accountable instanceof \App\Models\Client ? 'client' : 'agent') : '') == 'client' ? 'selected' : '' }}>{{ __('common.client') }}</option>
                    <option value="agent" {{ old('accountable_type', $accountable ? ($accountable instanceof \App\Models\Agent ? 'agent' : 'client') : '') == 'agent' ? 'selected' : '' }}>{{ __('common.agent') }}</option>
                </select>
                @error('accountable_type')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" id="accountable_id_group">
                <label class="form-label" for="accountable_id">{{ __('common.select_owner') }} <span class="text-error">*</span></label>
                <select id="accountable_id" name="accountable_id" class="form-select" required>
                    <option value="">{{ __('common.select_owner') }}</option>
                    @if($accountable)
                        @if($accountable instanceof \App\Models\Client)
                            <option value="{{ $accountable->id }}" selected>{{ $accountable->full_name }}</option>
                        @else
                            <option value="{{ $accountable->id }}" selected>{{ $accountable->full_name }}</option>
                        @endif
                    @else
                        <optgroup label="{{ __('common.clients') }}" id="clients_group">
                            @foreach($clients ?? [] as $client)
                                <option value="{{ $client->id }}" {{ old('accountable_id') == $client->id ? 'selected' : '' }}>{{ $client->full_name }}</option>
                            @endforeach
                        </optgroup>
                        <optgroup label="{{ __('common.agents') }}" id="agents_group">
                            @foreach($agents ?? [] as $agent)
                                <option value="{{ $agent->id }}" {{ old('accountable_id') == $agent->id ? 'selected' : '' }}>{{ $agent->full_name }}</option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
                @error('accountable_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" id="account_type_group">
                <label class="form-label" for="account_type">{{ __('common.account_type') }} <span class="text-error">*</span></label>
                <select id="account_type" name="account_type" class="form-select" required>
                    <option value="">{{ __('common.select_account_type') }}</option>
                    <option value="general" id="general_option" {{ old('account_type') == 'general' ? 'selected' : '' }}>{{ __('common.general') }}</option>
                    <option value="float" id="float_option" {{ old('account_type') == 'float' ? 'selected' : '' }}>{{ __('common.float') }}</option>
                    <option value="commission" id="commission_option" {{ old('account_type') == 'commission' ? 'selected' : '' }}>{{ __('common.commission') }}</option>
                </select>
                <small class="text-muted-sm" id="account_type_help"></small>
                @error('account_type')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="account_number">{{ __('common.account_number') }}</label>
                <input type="text" id="account_number" name="account_number" class="form-control" value="{{ old('account_number') }}" maxlength="50">
                <small class="text-muted-sm">{{ __('common.account_number_help') }}</small>
                @error('account_number')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="balance">{{ __('common.initial_balance') }}</label>
                <input type="number" id="balance" name="balance" class="form-control" value="{{ old('balance', 0) }}" step="0.01" min="0">
                @error('balance')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="currency">{{ __('common.currency') }} <span class="text-error">*</span></label>
                <input type="text" id="currency" name="currency" class="form-control" value="{{ old('currency', config('currency.default', 'CFA')) }}" maxlength="3" required>
                <small class="text-muted-sm">{{ __('common.currency_help') }}</small>
                @error('currency')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="status">{{ __('common.status') }} <span class="text-error">*</span></label>
                <select id="status" name="status" class="form-select" required>
                    <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>{{ __('common.active') }}</option>
                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>{{ __('common.inactive') }}</option>
                    <option value="suspended" {{ old('status') == 'suspended' ? 'selected' : '' }}>{{ __('common.suspended') }}</option>
                    <option value="closed" {{ old('status') == 'closed' ? 'selected' : '' }}>{{ __('common.closed') }}</option>
                </select>
                @error('status')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="opening_date">{{ __('common.opening_date') }} <span class="text-error">*</span></label>
                <input type="date" id="opening_date" name="opening_date" class="form-control" value="{{ old('opening_date', date('Y-m-d')) }}" required>
                @error('opening_date')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="closing_date">{{ __('common.closing_date') }}</label>
                <input type="date" id="closing_date" name="closing_date" class="form-control" value="{{ old('closing_date') }}">
                @error('closing_date')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <label class="form-label" for="description">{{ __('common.description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                @error('description')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="btn-row mt-30">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('accounts.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    function toggleAccountableSelect() {
        const accountableType = document.getElementById('accountable_type').value;
        const accountableId = document.getElementById('accountable_id');
        const accountType = document.getElementById('account_type');
        const accountTypeHelp = document.getElementById('account_type_help');
        
        // Show/hide client/agent options
        const clientsGroup = document.getElementById('clients_group');
        const agentsGroup = document.getElementById('agents_group');
        
        if (accountableType === 'client') {
            if (clientsGroup) clientsGroup.style.display = '';
            if (agentsGroup) agentsGroup.style.display = 'none';
            // For clients, only general accounts
            document.getElementById('float_option').style.display = 'none';
            document.getElementById('commission_option').style.display = 'none';
            accountType.value = 'general';
            accountTypeHelp.textContent = '{{ __('common.client_accounts_are_general') }}';
        } else if (accountableType === 'agent') {
            if (clientsGroup) clientsGroup.style.display = 'none';
            if (agentsGroup) agentsGroup.style.display = '';
            // For agents, only float and commission
            document.getElementById('general_option').style.display = 'none';
            document.getElementById('float_option').style.display = '';
            document.getElementById('commission_option').style.display = '';
            if (accountType.value === 'general') {
                accountType.value = 'float';
            }
            accountTypeHelp.textContent = '{{ __('common.agent_accounts_must_be_float_or_commission') }}';
        } else {
            if (clientsGroup) clientsGroup.style.display = '';
            if (agentsGroup) agentsGroup.style.display = '';
            document.getElementById('general_option').style.display = '';
            document.getElementById('float_option').style.display = '';
            document.getElementById('commission_option').style.display = '';
            accountTypeHelp.textContent = '';
        }
    }

    // Initialize on page load
    @if($accountable)
        document.getElementById('accountable_type').dispatchEvent(new Event('change'));
    @else
        toggleAccountableSelect();
    @endif
</script>
@endpush
@endsection

