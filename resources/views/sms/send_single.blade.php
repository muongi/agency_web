@extends('layouts.app')

@section('title', __('common.send_single_sms'))
@section('page-title', __('common.send_single_sms'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.send_single_sms') }}</h1>
    
    <div style="margin-bottom: 30px; padding: 20px; background: var(--bg-secondary); border-radius: 8px; border-left: 4px solid var(--accent-color);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
            <div>
                <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 5px;">{{ __('common.current_balance') }}</div>
                <div style="font-size: 24px; font-weight: bold; color: {{ $smsBalance > 0 ? 'var(--success-text)' : 'var(--error-text)' }};">
                    {{ number_format($smsBalance, 2) }} {{ __('common.sms_credits') }}
                </div>
            </div>
            <div>
                <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 5px;">{{ __('common.sender_number') }}</div>
                <div style="font-size: 16px; font-weight: 600; color: var(--text-primary);">
                    {{ $businessUnit->sms_sender_number }}
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('business.sms.store-single') }}" id="sendSmsForm">
        @csrf

        <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="client_id">{{ __('common.select_client') }} <span style="color: #ff6b6b;">*</span></label>
                <select id="client_id" name="client_id" class="form-select" required>
                    <option value="">{{ __('common.select_client') }}</option>
                    @foreach($clients as $client)
                        <option value="{{ $client->id }}" data-phone="{{ $client->phone_number }}" {{ old('client_id') == $client->id ? 'selected' : '' }}>
                            {{ $client->full_name }} - {{ $client->phone_number }}
                        </option>
                    @endforeach
                </select>
                @error('client_id')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
                <div id="recipient-preview" style="margin-top: 10px; padding: 10px; background: var(--bg-secondary); border-radius: 4px; display: none;">
                    <strong style="color: var(--text-primary);">{{ __('common.recipient_phone_number') }}:</strong>
                    <span id="recipient-phone" style="color: var(--text-secondary);"></span>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="message">{{ __('common.message') }} <span style="color: #ff6b6b;">*</span></label>
                <textarea id="message" name="message" class="form-control" rows="5" maxlength="160" required placeholder="{{ __('common.enter_sms_message') }}">{{ old('message') }}</textarea>
                <div style="margin-top: 5px; display: flex; justify-content: space-between; align-items: center;">
                    <span id="char-count" style="font-size: 12px; color: var(--text-muted);">0 / 160</span>
                    <span style="font-size: 12px; color: var(--text-muted);">{{ __('common.estimated_cost') }}: 1 {{ __('common.sms_credit') }}</span>
                </div>
                @error('message')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn" id="sendBtn">
                    <span>{{ __('common.send_sms') }}</span>
                </button>
                <a href="{{ route('business.sms.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const clientSelect = document.getElementById('client_id');
    const recipientPreview = document.getElementById('recipient-preview');
    const recipientPhone = document.getElementById('recipient-phone');
    const messageTextarea = document.getElementById('message');
    const charCount = document.getElementById('char-count');
    const sendBtn = document.getElementById('sendBtn');
    const form = document.getElementById('sendSmsForm');

    // Update recipient preview when client is selected
    clientSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const phone = selectedOption.getAttribute('data-phone');
        
        if (phone) {
            recipientPhone.textContent = phone;
            recipientPreview.style.display = 'block';
        } else {
            recipientPreview.style.display = 'none';
        }
    });

    // Character counter
    messageTextarea.addEventListener('input', function() {
        const length = this.value.length;
        charCount.textContent = length + ' / 160';
        
        if (length > 160) {
            charCount.style.color = 'var(--error-text)';
        } else if (length > 140) {
            charCount.style.color = 'var(--warning-text, #ffa500)';
        } else {
            charCount.style.color = 'var(--text-muted)';
        }
    });

    // Initialize character count
    messageTextarea.dispatchEvent(new Event('input'));

    // Show initial recipient if client is already selected
    if (clientSelect.value) {
        clientSelect.dispatchEvent(new Event('change'));
    }

    // Form submission with loading state
    form.addEventListener('submit', function(e) {
        if (!clientSelect.value || !messageTextarea.value.trim()) {
            e.preventDefault();
            alert('{{ __('common.please_fill_all_fields') }}');
            return;
        }

        sendBtn.disabled = true;
        sendBtn.innerHTML = '<span>{{ __('common.sending') }}...</span>';
    });
});
</script>
@endsection
