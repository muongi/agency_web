@extends('layouts.app')

@section('title', __('common.add_transaction_type'))
@section('page-title', __('common.add_transaction_type'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.add_transaction_type') }}</h1>
    
    @if($assignedTransactionTypes->count() > 0)
        <form method="POST" action="{{ route('business.config.transaction-types.store') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="transaction_type_id">{{ __('common.select_transaction_type') }} <span style="color: #ff6b6b;">*</span></label>
                <select id="transaction_type_id" name="transaction_type_id" class="form-select" required>
                    <option value="">{{ __('common.select_transaction_type') }}</option>
                    @foreach($assignedTransactionTypes as $transactionType)
                        <option value="{{ $transactionType->id }}" {{ old('transaction_type_id') == $transactionType->id ? 'selected' : '' }}>
                            {{ $transactionType->name }} ({{ $transactionType->code }})
                        </option>
                    @endforeach
                </select>
                <small style="color: var(--text-muted); font-size: 12px; margin-top: 5px; display: block;">
                    {{ __('common.select_from_assigned_types') }}
                </small>
                @error('transaction_type_id')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <!-- Display selected transaction type details -->
            <div id="transaction_type_details" style="display: none; margin-top: 20px; padding: 15px; background: #2d2d2d; border-radius: 4px;">
                <h3 style="color: var(--accent-color); margin-bottom: 10px;" id="selected_name"></h3>
                <p style="margin-bottom: 5px;"><strong>{{ __('common.code') }}:</strong> <code id="selected_code"></code></p>
                <p id="selected_description" style="margin-top: 10px;"></p>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 30px;">
                <button type="submit" class="btn">{{ __('common.add') }}</button>
                <a href="{{ route('business.config.transaction-types.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
            </div>
        </form>
    @else
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <p style="margin-bottom: 20px;">{{ __('common.no_assigned_transaction_types') }}</p>
            <p style="margin-bottom: 20px; font-size: 14px;">{{ __('common.contact_superadmin_to_assign_types') }}</p>
            <a href="{{ route('business.config.transaction-types.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
        </div>
    @endif
</div>

@push('scripts')
<script>
    const transactionTypes = @json($assignedTransactionTypes->keyBy('id'));
    const selectElement = document.getElementById('transaction_type_id');
    const detailsDiv = document.getElementById('transaction_type_details');
    
    if (selectElement) {
        selectElement.addEventListener('change', function() {
            const selectedId = this.value;
            if (selectedId && transactionTypes[selectedId]) {
                const type = transactionTypes[selectedId];
                document.getElementById('selected_name').textContent = type.name;
                document.getElementById('selected_code').textContent = type.code;
                document.getElementById('selected_description').textContent = type.description || '{{ __('common.no_description') }}';
                detailsDiv.style.display = 'block';
            } else {
                detailsDiv.style.display = 'none';
            }
        });
        
        // Trigger on page load if there's an old value
        if (selectElement.value) {
            selectElement.dispatchEvent(new Event('change'));
        }
    }
</script>
@endpush
@endsection
