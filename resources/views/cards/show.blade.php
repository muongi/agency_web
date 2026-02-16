@extends('layouts.app')

@section('title', __('common.card_details'))
@section('page-title', __('common.card_details'))

@section('content')
@php
    $user = auth()->user();
    $businessUnit = $user->currentBusinessUnit;
    $canEdit = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('edit-card', $businessUnit->id));
    $canActivate = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('activate-card', $businessUnit->id));
    $canBlock = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('block-card', $businessUnit->id));
    $canReplace = $user->isSuperAdmin() || ($businessUnit && $user->hasPermission('replace-card', $businessUnit->id));
@endphp

<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: var(--accent-color);">{{ __('common.card_details') }}</h1>
        <div style="display: flex; gap: 10px;">
            @if($canEdit)
                <a href="{{ route('cards.edit', $card->id) }}" class="btn">{{ __('common.edit') }}</a>
            @endif
            <a href="{{ route('cards.index') }}" class="btn btn-secondary">{{ __('common.back') }}</a>
        </div>
    </div>

    <!-- Card Header -->
    <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px; margin-bottom: 30px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div>
                <h2 style="color: var(--accent-color); margin-bottom: 10px; font-size: 24px;">{{ $card->card_number }}</h2>
                @php
                    $statusColors = [
                        'active' => 'var(--success-bg)',
                        'inactive' => 'var(--bg-primary)',
                        'blocked' => 'var(--error-bg)',
                        'expired' => 'var(--warning-bg)',
                        'replaced' => 'var(--text-muted)',
                    ];
                    $statusColor = $statusColors[$card->status] ?? 'var(--bg-tertiary)';
                @endphp
                <span style="padding: 6px 16px; border-radius: 4px; background: {{ $statusColor }}; color: var(--text-primary); font-size: 14px; font-weight: 500;">
                    {{ __('common.' . $card->status) }}
                </span>
            </div>
            <div style="text-align: right;">
                @if($card->isReplaced() && $card->replacedByCard)
                    <p style="color: var(--text-muted); margin: 5px 0;">
                        <strong>{{ __('common.replaced_by') }}:</strong> 
                        <a href="{{ route('cards.show', $card->replacedByCard->id) }}" style="color: var(--accent-color);">
                            {{ $card->replacedByCard->card_number }}
                        </a>
                    </p>
                @endif
                @if($card->replacedCard)
                    <p style="color: var(--text-muted); margin: 5px 0;">
                        <strong>{{ __('common.replaces') }}:</strong> 
                        <a href="{{ route('cards.show', $card->replacedCard->id) }}" style="color: var(--accent-color);">
                            {{ $card->replacedCard->card_number }}
                        </a>
                    </p>
                @endif
            </div>
        </div>
    </div>

    <!-- Card Information -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div>
            <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.account') }}:</strong>
            <p style="color: var(--text-primary); margin: 0 0 15px 0;">
                <a href="{{ route('accounts.show', $card->account->id) }}" style="color: var(--accent-color);">
                    {{ $card->account->account_number }}
                </a>
            </p>
        </div>

        <div>
            <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.client') }}:</strong>
            <p style="color: var(--text-primary); margin: 0 0 15px 0;">
                @if($card->account->accountable_type == 'App\Models\Client')
                    <a href="{{ route('clients.show', $card->account->accountable_id) }}" style="color: var(--accent-color);">
                        {{ $card->account->accountable->full_name ?? 'N/A' }}
                    </a>
                @else
                    N/A
                @endif
            </p>
        </div>

        <div>
            <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.expiration_date') }}:</strong>
            <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->expiration_date->format('Y-m-d') }}</p>
        </div>

        <div>
            <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.issued_at') }}:</strong>
            <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->issued_at ? $card->issued_at->format('Y-m-d H:i') : '-' }}</p>
        </div>

        <div>
            <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.activated_at') }}:</strong>
            <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->activated_at ? $card->activated_at->format('Y-m-d H:i') : '-' }}</p>
        </div>

        @if($card->card_serial)
            <div>
                <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.card_serial') }}:</strong>
                <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->card_serial }}</p>
            </div>
        @endif

        @if($card->written_at)
            <div>
                <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.written_at') }}:</strong>
                <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->written_at->format('Y-m-d H:i') }}</p>
            </div>
        @endif

        @if($card->written_by)
            <div>
                <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.written_by') }}:</strong>
                <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->written_by }}</p>
            </div>
        @endif

        <div>
            <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.daily_transaction_limit') }}:</strong>
            <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->daily_transaction_limit ? number_format($card->daily_transaction_limit, 2) : '-' }}</p>
        </div>

        <div>
            <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.daily_withdrawal_limit') }}:</strong>
            <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->daily_withdrawal_limit ? number_format($card->daily_withdrawal_limit, 2) : '-' }}</p>
        </div>

        @if($card->blocked_at)
            <div>
                <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.blocked_at') }}:</strong>
                <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->blocked_at->format('Y-m-d H:i') }}</p>
            </div>
            @if($card->blocked_reason)
                <div>
                    <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.blocked_reason') }}:</strong>
                    <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->blocked_reason }}</p>
                </div>
            @endif
        @endif

        @if($card->replacement_reason)
            <div>
                <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.replacement_reason') }}:</strong>
                <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->replacement_reason }}</p>
            </div>
        @endif

        @if($card->description)
            <div style="grid-column: 1 / -1;">
                <strong style="color: var(--accent-color); display: block; margin-bottom: 5px;">{{ __('common.description') }}:</strong>
                <p style="color: var(--text-primary); margin: 0 0 15px 0;">{{ $card->description }}</p>
            </div>
        @endif
    </div>

    <!-- Actions -->
    <div style="padding: 20px; background: var(--bg-tertiary); border-radius: 8px;">
        <h3 style="color: var(--accent-color); margin-bottom: 15px;">{{ __('common.actions') }}</h3>
        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            @if($canActivate && $card->canBeActivated())
                <form action="{{ route('cards.activate', $card->id) }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn" onclick="return confirm('{{ __('common.confirm_activate_card') }}');">
                        {{ __('common.activate') }}
                    </button>
                </form>
            @endif

            @if($canBlock && $card->canBeBlocked())
                <button type="button" class="btn" style="background: var(--error-bg);" onclick="showBlockModal()">
                    {{ __('common.block') }}
                </button>
            @endif

            @if($canReplace && $card->canBeReplaced())
                <button type="button" class="btn" style="background: var(--warning-bg);" onclick="showReplaceModal()">
                    {{ __('common.replace') }}
                </button>
            @endif
        </div>
    </div>

    <!-- Block Modal -->
    @if($canBlock && $card->canBeBlocked())
        <div id="blockModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: var(--bg-primary); padding: 30px; border-radius: 8px; max-width: 500px; width: 90%;">
                <h3 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.block_card') }}</h3>
                <form action="{{ route('cards.block', $card->id) }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">{{ __('common.reason') }} ({{ __('common.optional') }})</label>
                        <textarea name="reason" class="form-control" rows="3"></textarea>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn" style="background: var(--error-bg);">{{ __('common.block') }}</button>
                        <button type="button" class="btn btn-secondary" onclick="hideBlockModal()">{{ __('common.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Replace Modal -->
    @if($canReplace && $card->canBeReplaced())
        <div id="replaceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
            <div style="background: var(--bg-primary); padding: 30px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
                <h3 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.replace_card') }}</h3>
                <form action="{{ route('cards.replace', $card->id) }}" method="POST">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">{{ __('common.reason') }} ({{ __('common.optional') }})</label>
                        <textarea name="reason" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('common.expiration_date') }}</label>
                        <input type="date" name="expiration_date" class="form-control" 
                               value="{{ $card->expiration_date->format('Y-m-d') }}" 
                               min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                        <small style="color: var(--text-muted);">{{ __('common.leave_empty_to_use_current') }}</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('common.daily_transaction_limit') }}</label>
                        <input type="number" name="daily_transaction_limit" class="form-control" 
                               step="0.01" min="0" value="{{ $card->daily_transaction_limit }}">
                        <small style="color: var(--text-muted);">{{ __('common.leave_empty_to_use_current') }}</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('common.daily_withdrawal_limit') }}</label>
                        <input type="number" name="daily_withdrawal_limit" class="form-control" 
                               step="0.01" min="0" value="{{ $card->daily_withdrawal_limit }}">
                        <small style="color: var(--text-muted);">{{ __('common.leave_empty_to_use_current') }}</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">{{ __('common.pin') }}</label>
                        <input type="text" name="pin" class="form-control" maxlength="6" minlength="4">
                        <small style="color: var(--text-muted);">{{ __('common.leave_empty_to_keep_current') }}</small>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" class="btn" style="background: var(--warning-bg);">{{ __('common.replace') }}</button>
                        <button type="button" class="btn btn-secondary" onclick="hideReplaceModal()">{{ __('common.cancel') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@if($canBlock && $card->canBeBlocked())
<script>
function showBlockModal() {
    document.getElementById('blockModal').style.display = 'flex';
}
function hideBlockModal() {
    document.getElementById('blockModal').style.display = 'none';
}
</script>
@endif

@if($canReplace && $card->canBeReplaced())
<script>
function showReplaceModal() {
    document.getElementById('replaceModal').style.display = 'flex';
}
function hideReplaceModal() {
    document.getElementById('replaceModal').style.display = 'none';
}
</script>
@endif
@endsection

