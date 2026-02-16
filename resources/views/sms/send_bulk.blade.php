@extends('layouts.app')

@section('title', __('common.send_bulk_sms'))
@section('page-title', __('common.send_bulk_sms'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.send_bulk_sms') }}</h1>
    
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
            <div>
                <div style="color: var(--text-muted); font-size: 14px; margin-bottom: 5px;">{{ __('common.selected_recipients') }}</div>
                <div style="font-size: 24px; font-weight: bold; color: var(--text-primary);">
                    <span id="selected-count">0</span>
                </div>
                <div style="font-size: 12px; color: var(--text-muted); margin-top: 5px;">
                    {{ __('common.estimated_cost') }}: <span id="estimated-cost">0</span> {{ __('common.sms_credits') }}
                </div>
            </div>
        </div>
    </div>

    <form method="POST" action="{{ route('business.sms.store-bulk') }}" id="sendBulkSmsForm">
        @csrf

        <div style="display: grid; grid-template-columns: 1fr; gap: 20px;">
            @if(isset($groups) && $groups->count() > 0)
            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <label class="form-label" style="margin: 0;">{{ __('common.select_groups') }}</label>
                </div>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 4px; padding: 10px; background: var(--bg-primary);">
                    <div id="groups-list">
                        @foreach($groups as $group)
                            @php
                                $groupClientIds = $group->clients->pluck('id')->toArray();
                            @endphp
                            <div class="group-item" style="padding: 8px; border-bottom: 1px solid var(--border-color);">
                                <label style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                                    <input type="checkbox" name="group_ids[]" value="{{ $group->id }}" class="group-checkbox" data-group-id="{{ $group->id }}" data-client-ids="{{ json_encode($groupClientIds) }}" onchange="handleGroupSelection(this)" style="margin-right: 10px;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--accent-color);">{{ $group->name }}</div>
                                        <div style="font-size: 12px; color: var(--text-muted);">{{ $group->clients_count }} {{ __('common.members') }}</div>
                                    </div>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            @endif

            <div class="form-group">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                    <label class="form-label" style="margin: 0;">{{ __('common.select_clients') }} <span style="color: #ff6b6b;">*</span></label>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn btn-secondary" style="padding: 5px 15px; font-size: 12px;" onclick="selectAllClients()">{{ __('common.select_all') }}</button>
                        <button type="button" class="btn btn-secondary" style="padding: 5px 15px; font-size: 12px;" onclick="deselectAllClients()">{{ __('common.deselect_all') }}</button>
                    </div>
                </div>
                <div style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 4px; padding: 10px; background: var(--bg-primary);">
                    @if($clients->count() > 0)
                        <input type="text" id="client-search" class="form-control" style="margin-bottom: 15px;" placeholder="{{ __('common.search_clients') }}" onkeyup="filterClients()">
                        <div id="clients-list">
                            @foreach($clients as $client)
                                <div class="client-item" style="padding: 8px; border-bottom: 1px solid var(--border-color);">
                                    <label style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                                        <input type="checkbox" name="client_ids[]" value="{{ $client->id }}" class="client-checkbox" data-name="{{ strtolower($client->full_name) }}" data-phone="{{ $client->phone_number }}" onchange="updateSelectionCount()" style="margin-right: 10px;">
                                        <div style="flex: 1;">
                                            <div style="font-weight: 600; color: var(--text-primary);">{{ $client->full_name }}</div>
                                            <div style="font-size: 12px; color: var(--text-muted);">{{ $client->phone_number }}</div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div style="padding: 20px; text-align: center; color: var(--text-muted);">
                            {{ __('common.no_clients_with_phone_numbers') }}
                        </div>
                    @endif
                </div>
                @error('client_ids')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
                @if($clients->count() == 0)
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ __('common.no_clients_with_phone_numbers') }}</div>
                @endif
            </div>

            <div class="form-group">
                <label class="form-label" for="message">{{ __('common.message') }} <span style="color: #ff6b6b;">*</span></label>
                <textarea id="message" name="message" class="form-control" rows="5" maxlength="160" required placeholder="{{ __('common.enter_sms_message') }}">{{ old('message') }}</textarea>
                <div style="margin-top: 5px; display: flex; justify-content: space-between; align-items: center;">
                    <span id="char-count" style="font-size: 12px; color: var(--text-muted);">0 / 160</span>
                    <span style="font-size: 12px; color: var(--text-muted);">{{ __('common.message_will_be_sent_to_all_selected_recipients') }}</span>
                </div>
                @error('message')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn" id="sendBtn" {{ $clients->count() == 0 ? 'disabled' : '' }}>
                    <span>{{ __('common.send_bulk_sms') }}</span>
                </button>
                <a href="{{ route('business.sms.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            </div>
        </div>
    </form>
</div>

<script>
function selectAllClients() {
    const checkboxes = document.querySelectorAll('.client-item:not([style*="display: none"]) .client-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
    updateSelectionCount();
}

function deselectAllClients() {
    const checkboxes = document.querySelectorAll('.client-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
    updateSelectionCount();
}

function filterClients() {
    const searchTerm = document.getElementById('client-search').value.toLowerCase();
    const clientItems = document.querySelectorAll('.client-item');
    
    clientItems.forEach(item => {
        const checkbox = item.querySelector('.client-checkbox');
        const name = checkbox.getAttribute('data-name');
        const phone = checkbox.getAttribute('data-phone') || '';
        
        if (name.includes(searchTerm) || phone.includes(searchTerm)) {
            item.style.display = 'block';
        } else {
            item.style.display = 'none';
        }
    });
    
    updateSelectionCount();
}

function handleGroupSelection(groupCheckbox) {
    const clientIds = JSON.parse(groupCheckbox.getAttribute('data-client-ids') || '[]');
    const isChecked = groupCheckbox.checked;
    
    clientIds.forEach(clientId => {
        const clientCheckbox = document.querySelector(`.client-checkbox[value="${clientId}"]`);
        if (clientCheckbox) {
            clientCheckbox.checked = isChecked;
            // Mark as selected by group for visual feedback
            if (isChecked) {
                clientCheckbox.closest('.client-item').style.backgroundColor = 'var(--success-bg)';
                clientCheckbox.closest('.client-item').style.opacity = '0.8';
            } else {
                clientCheckbox.closest('.client-item').style.backgroundColor = '';
                clientCheckbox.closest('.client-item').style.opacity = '1';
            }
        }
    });
    
    updateSelectionCount();
}

function updateSelectionCount() {
    const checkedBoxes = document.querySelectorAll('.client-checkbox:checked');
    const count = checkedBoxes.length;
    const selectedCountEl = document.getElementById('selected-count');
    const estimatedCostEl = document.getElementById('estimated-cost');
    const sendBtn = document.getElementById('sendBtn');
    
    selectedCountEl.textContent = count;
    estimatedCostEl.textContent = count;
    
    // Update button state
    if (sendBtn) {
        sendBtn.disabled = count === 0 || !document.getElementById('message').value.trim();
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const messageTextarea = document.getElementById('message');
    const charCount = document.getElementById('char-count');
    const sendBtn = document.getElementById('sendBtn');
    const form = document.getElementById('sendBulkSmsForm');
    const checkboxes = document.querySelectorAll('.client-checkbox');

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
        
        updateSelectionCount();
    });

    // Update selection count when checkboxes change
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectionCount);
    });

    // Initialize character count
    messageTextarea.dispatchEvent(new Event('input'));
    updateSelectionCount();

    // Form submission with loading state
    form.addEventListener('submit', function(e) {
        const checkedBoxes = document.querySelectorAll('.client-checkbox:checked');
        
        if (checkedBoxes.length === 0) {
            e.preventDefault();
            alert('{{ __('common.please_select_at_least_one_client') }}');
            return;
        }

        if (!messageTextarea.value.trim()) {
            e.preventDefault();
            alert('{{ __('common.please_enter_a_message') }}');
            return;
        }

        sendBtn.disabled = true;
        sendBtn.innerHTML = '<span>{{ __('common.sending') }}...</span>';
    });
});
</script>
@endsection
