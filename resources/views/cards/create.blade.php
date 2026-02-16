@extends('layouts.app')

@section('title', __('common.add_card'))
@section('page-title', __('common.add_card'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.add_card') }}</h1>
    
    <form method="POST" action="{{ route('cards.store') }}">
        @csrf

        <div style="padding: 15px; background: var(--bg-tertiary); border-radius: 8px; margin-bottom: 20px;">
            <strong>{{ __('common.next_card_number') }}:</strong> 
            <span style="color: var(--accent-color); font-size: 18px; font-weight: bold;">{{ $nextCardNumber }}</span>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="account_id">{{ __('common.account') }} <span style="color: #ff6b6b;">*</span></label>
                <select id="account_id" name="account_id" class="form-select" required>
                    <option value="">{{ __('common.select_account') }}</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" {{ old('account_id') == $account->id ? 'selected' : '' }}>
                            {{ $account->account_number }} - {{ $account->accountable->full_name ?? 'N/A' }}
                        </option>
                    @endforeach
                </select>
                @error('account_id')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="card_serial">{{ __('common.card_serial') }}</label>
                <input type="text" id="card_serial" name="card_serial" class="form-control" 
                       maxlength="32" 
                       value="{{ old('card_serial') }}" 
                       placeholder="{{ __('common.card_serial') }}">
                <small style="color: var(--text-muted);">{{ __('common.card_serial_help') }}</small>
                @error('card_serial')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="expiration_date">{{ __('common.expiration_date') }}</label>
                <input type="date" id="expiration_date" name="expiration_date" class="form-control" 
                       value="{{ old('expiration_date', $cardSettings->getDefaultExpirationDate()->format('Y-m-d')) }}" 
                       min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                <small style="color: var(--text-muted);">{{ __('common.default_expiration') }}: {{ $cardSettings->default_expiration_years }} {{ __('common.years') }}</small>
                @error('expiration_date')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="daily_transaction_limit">{{ __('common.daily_transaction_limit') }}</label>
                <input type="number" id="daily_transaction_limit" name="daily_transaction_limit" 
                       class="form-control" step="0.01" min="0" 
                       value="{{ old('daily_transaction_limit', $cardSettings->default_daily_transaction_limit) }}">
                <small style="color: var(--text-muted);">{{ __('common.leave_empty_for_default') }}</small>
                @error('daily_transaction_limit')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="daily_withdrawal_limit">{{ __('common.daily_withdrawal_limit') }}</label>
                <input type="number" id="daily_withdrawal_limit" name="daily_withdrawal_limit" 
                       class="form-control" step="0.01" min="0" 
                       value="{{ old('daily_withdrawal_limit', $cardSettings->default_daily_withdrawal_limit) }}">
                <small style="color: var(--text-muted);">{{ __('common.leave_empty_for_default') }}</small>
                @error('daily_withdrawal_limit')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="pin">{{ __('common.pin') }}</label>
                <input type="text" id="pin" name="pin" class="form-control" 
                       maxlength="6" minlength="4" 
                       value="{{ old('pin') }}" 
                       placeholder="{{ __('common.enter_4_to_6_digits') }}">
                <small style="color: var(--text-muted);">{{ __('common.pin_optional_can_set_later') }}</small>
                @error('pin')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="description">{{ __('common.description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                @error('description')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('cards.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

