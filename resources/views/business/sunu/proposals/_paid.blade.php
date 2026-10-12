{{-- First premium paid = subscription complete. Nothing shown for rejected / cancelled proposals. --}}
@unless(in_array($proposal->status, ['rejected', 'cancelled'], true))
    @if($proposal->isFirstPremiumPaid())
        <span class="badge" style="background:#27ae60;color:#fff;">{{ __('common.sunu_first_premium_paid') }}</span>
    @else
        <span class="badge" style="background:#95a5a6;color:#fff;">{{ __('common.sunu_first_premium_unpaid') }}</span>
    @endif
@endunless
