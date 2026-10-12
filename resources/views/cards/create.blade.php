@extends('layouts.app')

@section('title', __('common.add_card'))
@section('page-title', __('common.add_card'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 8px; color: var(--accent-color);">{{ __('common.add_card') }}</h1>
    <p class="muted" style="margin: 0 0 24px;">{{ __('common.sunu_card_create_help') }}</p>

    @if($clients->isEmpty())
        <div style="padding: 15px; background: var(--bg-tertiary); border-radius: 8px; margin-bottom: 20px;">
            {{ __('common.sunu_card_no_registered_clients') }}
        </div>
    @endif

    <form method="POST" action="{{ route('cards.store') }}">
        @csrf

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group detail-full">
                <label class="form-label" for="client_id">{{ __('common.sunu_card_client') }} <span class="text-error">*</span></label>
                <input type="search" id="client_filter" class="form-control" style="margin-bottom: 8px;"
                       placeholder="{{ __('common.sunu_card_client_filter') }}" autocomplete="off">
                <select id="client_id" name="client_id" class="form-select" required>
                    <option value="">{{ __('common.sunu_card_select_client') }}</option>
                    @foreach($clients as $client)
                        <option value="{{ $client['id'] }}" {{ (string) old('client_id', request('client_id')) === (string) $client['id'] ? 'selected' : '' }}>
                            {{ $client['label'] }}{{ $client['has_card'] ? ' — ' . __('common.sunu_card_already_has_card') : '' }}
                        </option>
                    @endforeach
                </select>
                <small class="muted">{{ __('common.sunu_card_client_help') }}</small>
                @error('client_id')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="card_uid">{{ __('common.sunu_card_uid') }} <span class="text-error">*</span></label>
                <input type="text" id="card_uid" name="card_uid" class="form-control"
                       maxlength="64" required autocomplete="off" list="stock_uids"
                       value="{{ old('card_uid', $prefillUid) }}"
                       placeholder="{{ __('common.sunu_card_uid_placeholder') }}">
                <datalist id="stock_uids">
                    @foreach($stockUids as $stockUid)
                        <option value="{{ $stockUid }}"></option>
                    @endforeach
                </datalist>
                <small class="muted">{{ __('common.sunu_card_uid_help') }}
                    @if($stockUids->isNotEmpty())
                        {{ __('common.sunu_card_stock_available', ['count' => $stockUids->count()]) }}
                    @endif
                </small>
                @error('card_uid')
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
            <a href="{{ route('cards.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>

<script>
    // Narrow the client dropdown by name, phone or policy/proposal number
    (function () {
        var filter = document.getElementById('client_filter');
        var select = document.getElementById('client_id');
        if (!filter || !select) return;
        filter.addEventListener('input', function () {
            var term = filter.value.trim().toLowerCase();
            var firstMatch = null;
            Array.prototype.forEach.call(select.options, function (opt) {
                if (!opt.value) return;
                var match = !term || opt.text.toLowerCase().indexOf(term) !== -1;
                opt.hidden = !match;
                if (match && !firstMatch) firstMatch = opt;
            });
            if (term && firstMatch && (select.selectedIndex <= 0 || select.options[select.selectedIndex].hidden)) {
                select.value = firstMatch.value;
            }
        });
    })();
</script>
@endsection
