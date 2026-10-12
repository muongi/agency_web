@extends('layouts.app')

@section('title', __('common.edit_account'))
@section('page-title', __('common.edit_account'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.edit_account') }}</h1>
    
    <form method="POST" action="{{ route('accounts.update', $account->id) }}">
        @csrf
        @method('PUT')

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="account_number">{{ __('common.account_number') }}</label>
                <input type="text" id="account_number" name="account_number" class="form-control" value="{{ old('account_number', $account->account_number) }}" maxlength="50" required>
                <small class="text-muted-sm">
                    @if($account->isRequired())
                        {{ __('common.account_number_cbs_help') }}
                    @else
                        {{ __('common.account_number_help') }}
                    @endif
                </small>
                @error('account_number')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="balance">{{ __('common.balance') }}</label>
                <input type="number" id="balance" name="balance" class="form-control" value="{{ old('balance', $account->balance) }}" step="0.01" min="0">
                @error('balance')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="currency">{{ __('common.currency') }} <span class="text-error">*</span></label>
                <input type="text" id="currency" name="currency" class="form-control" value="{{ old('currency', $account->currency) }}" maxlength="3" required>
                <small class="text-muted-sm">{{ __('common.currency_help') }}</small>
                @error('currency')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="status">{{ __('common.status') }} <span class="text-error">*</span></label>
                <select id="status" name="status" class="form-select" required>
                    <option value="active" {{ old('status', $account->status) == 'active' ? 'selected' : '' }}>{{ __('common.active') }}</option>
                    <option value="inactive" {{ old('status', $account->status) == 'inactive' ? 'selected' : '' }}>{{ __('common.inactive') }}</option>
                    <option value="suspended" {{ old('status', $account->status) == 'suspended' ? 'selected' : '' }}>{{ __('common.suspended') }}</option>
                    <option value="closed" {{ old('status', $account->status) == 'closed' ? 'selected' : '' }}>{{ __('common.closed') }}</option>
                </select>
                @error('status')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="opening_date">{{ __('common.opening_date') }} <span class="text-error">*</span></label>
                <input type="date" id="opening_date" name="opening_date" class="form-control" value="{{ old('opening_date', $account->opening_date->format('Y-m-d')) }}" required>
                @error('opening_date')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="closing_date">{{ __('common.closing_date') }}</label>
                <input type="date" id="closing_date" name="closing_date" class="form-control" value="{{ old('closing_date', $account->closing_date ? $account->closing_date->format('Y-m-d') : '') }}">
                @error('closing_date')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <label class="form-label" for="description">{{ __('common.description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $account->description) }}</textarea>
                @error('description')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <div style="padding: 15px; background: var(--bg-tertiary); border-radius: 4px; border-left: 3px solid var(--accent-color);">
                    <strong class="text-accent">{{ __('common.account_owner') }}:</strong>
                    <p style="color: var(--text-primary); margin-top: 5px;">
                        {{ $account->accountable->full_name ?? 'N/A' }}
                        <span class="muted">({{ $account->isForClient() ? __('common.client') : __('common.agent') }})</span>
                    </p>
                    <p style="color: var(--text-muted); font-size: 12px; margin-top: 5px;">{{ __('common.account_owner_cannot_be_changed') }}</p>
                </div>
            </div>
        </div>

        <div class="btn-row mt-30">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('accounts.show', $account->id) }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

