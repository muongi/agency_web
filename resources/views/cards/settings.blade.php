@extends('layouts.app')

@section('title', __('common.card_settings'))
@section('page-title', __('common.card_settings'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.card_settings') }}</h1>
    
    <form method="POST" action="{{ route('cards.settings.update') }}">
        @csrf

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="card_prefix">{{ __('common.card_prefix') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="card_prefix" name="card_prefix" class="form-control" 
                       value="{{ old('card_prefix', $cardSettings->card_prefix) }}" 
                       required maxlength="20">
                <small style="color: var(--text-muted);">{{ __('common.card_prefix_description') }}</small>
                @error('card_prefix')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="auto_increment_length">{{ __('common.auto_increment_length') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="number" id="auto_increment_length" name="auto_increment_length" class="form-control" 
                       value="{{ old('auto_increment_length', $cardSettings->auto_increment_length) }}" 
                       required min="4" max="10">
                <small style="color: var(--text-muted);">{{ __('common.auto_increment_length_description') }}</small>
                @error('auto_increment_length')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="default_expiration_years">{{ __('common.default_expiration_years') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="number" id="default_expiration_years" name="default_expiration_years" class="form-control" 
                       value="{{ old('default_expiration_years', $cardSettings->default_expiration_years) }}" 
                       required min="1" max="10">
                <small style="color: var(--text-muted);">{{ __('common.default_expiration_years_description') }}</small>
                @error('default_expiration_years')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="default_daily_transaction_limit">{{ __('common.default_daily_transaction_limit') }}</label>
                <input type="number" id="default_daily_transaction_limit" name="default_daily_transaction_limit" 
                       class="form-control" step="0.01" min="0" 
                       value="{{ old('default_daily_transaction_limit', $cardSettings->default_daily_transaction_limit) }}">
                <small style="color: var(--text-muted);">{{ __('common.leave_empty_for_no_limit') }}</small>
                @error('default_daily_transaction_limit')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="default_daily_withdrawal_limit">{{ __('common.default_daily_withdrawal_limit') }}</label>
                <input type="number" id="default_daily_withdrawal_limit" name="default_daily_withdrawal_limit" 
                       class="form-control" step="0.01" min="0" 
                       value="{{ old('default_daily_withdrawal_limit', $cardSettings->default_daily_withdrawal_limit) }}">
                <small style="color: var(--text-muted);">{{ __('common.leave_empty_for_no_limit') }}</small>
                @error('default_daily_withdrawal_limit')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div style="padding: 15px; background: var(--bg-tertiary); border-radius: 8px; margin: 20px 0;">
            <strong>{{ __('common.next_card_number_preview') }}:</strong> 
            <span style="color: var(--accent-color); font-size: 18px; font-weight: bold;" id="previewCardNumber">
                {{ $cardSettings->card_prefix }}{{ str_pad(1, $cardSettings->auto_increment_length, '0', STR_PAD_LEFT) }}
            </span>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn">{{ __('common.save') }}</button>
            <a href="{{ route('cards.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>

<script>
document.getElementById('card_prefix').addEventListener('input', updatePreview);
document.getElementById('auto_increment_length').addEventListener('input', updatePreview);

function updatePreview() {
    const prefix = document.getElementById('card_prefix').value || '0000';
    const length = parseInt(document.getElementById('auto_increment_length').value) || 6;
    const preview = prefix + '0'.repeat(length);
    document.getElementById('previewCardNumber').textContent = preview;
}
</script>
@endsection

