@extends('layouts.app')

@section('title', __('common.edit_card'))
@section('page-title', __('common.edit_card'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.edit_card') }}</h1>
    
    <form method="POST" action="{{ route('cards.update', $card->id) }}">
        @csrf
        @method('PUT')

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.card_number') }}</label>
                <input type="text" class="form-control" value="{{ $card->card_number }}" disabled>
                <small style="color: var(--text-muted);">{{ __('common.card_number_cannot_be_changed') }}</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="card_serial">{{ __('common.card_serial') }}</label>
                @if($card->written_at)
                    <input type="text" class="form-control" value="{{ $card->card_serial ?? '-' }}" disabled>
                    <small style="color: var(--text-muted);">{{ __('common.card_serial_cannot_be_changed_after_written') }}</small>
                @else
                    <input type="text" id="card_serial" name="card_serial" class="form-control" 
                           maxlength="32" 
                           value="{{ old('card_serial', $card->card_serial) }}" 
                           placeholder="{{ __('common.card_serial') }}">
                    <small style="color: var(--text-muted);">{{ __('common.card_serial_help') }}</small>
                @endif
                @error('card_serial')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">{{ __('common.status') }}</label>
                <select name="status" class="form-select">
                    <option value="active" {{ old('status', $card->status) == 'active' ? 'selected' : '' }}>{{ __('common.active') }}</option>
                    <option value="inactive" {{ old('status', $card->status) == 'inactive' ? 'selected' : '' }}>{{ __('common.inactive') }}</option>
                    <option value="blocked" {{ old('status', $card->status) == 'blocked' ? 'selected' : '' }}>{{ __('common.blocked') }}</option>
                </select>
                @error('status')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="expiration_date">{{ __('common.expiration_date') }}</label>
                <input type="date" id="expiration_date" name="expiration_date" class="form-control" 
                       value="{{ old('expiration_date', $card->expiration_date->format('Y-m-d')) }}" 
                       min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                @error('expiration_date')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="daily_transaction_limit">{{ __('common.daily_transaction_limit') }}</label>
                <input type="number" id="daily_transaction_limit" name="daily_transaction_limit" 
                       class="form-control" step="0.01" min="0" 
                       value="{{ old('daily_transaction_limit', $card->daily_transaction_limit) }}">
                @error('daily_transaction_limit')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="daily_withdrawal_limit">{{ __('common.daily_withdrawal_limit') }}</label>
                <input type="number" id="daily_withdrawal_limit" name="daily_withdrawal_limit" 
                       class="form-control" step="0.01" min="0" 
                       value="{{ old('daily_withdrawal_limit', $card->daily_withdrawal_limit) }}">
                @error('daily_withdrawal_limit')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="pin">{{ __('common.pin') }}</label>
                <input type="text" id="pin" name="pin" class="form-control" 
                       maxlength="6" minlength="4" 
                       placeholder="{{ __('common.enter_new_pin_or_leave_empty') }}">
                <small style="color: var(--text-muted);">{{ __('common.only_enter_if_changing_pin') }}</small>
                @error('pin')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="description">{{ __('common.description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $card->description) }}</textarea>
                @error('description')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('cards.show', $card->id) }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

