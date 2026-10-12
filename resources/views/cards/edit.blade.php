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
                <small class="muted">{{ __('common.card_number_cannot_be_changed') }}</small>
            </div>

            <div class="form-group">
                <label class="form-label" for="card_serial">{{ __('common.card_serial') }}</label>
                @if($card->written_at)
                    <input type="text" class="form-control" value="{{ $card->card_serial ?? '-' }}" disabled>
                    <small class="muted">{{ __('common.card_serial_cannot_be_changed_after_written') }}</small>
                @else
                    <input type="text" id="card_serial" name="card_serial" class="form-control" 
                           maxlength="32" 
                           value="{{ old('card_serial', $card->card_serial) }}" 
                           placeholder="{{ __('common.card_serial') }}">
                    <small class="muted">{{ __('common.card_serial_help') }}</small>
                @endif
                @error('card_serial')
                    <div class="field-error">{{ $message }}</div>
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
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="expiration_date">{{ __('common.expiration_date') }}</label>
                <input type="date" id="expiration_date" name="expiration_date" class="form-control" 
                       value="{{ old('expiration_date', $card->expiration_date?->format('Y-m-d')) }}" 
                       min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                @error('expiration_date')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <label class="form-label" for="description">{{ __('common.description') }}</label>
                <textarea id="description" name="description" class="form-control" rows="3">{{ old('description', $card->description) }}</textarea>
                @error('description')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="btn-row mt-30">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('cards.show', $card->id) }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

