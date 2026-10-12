@if($status['is_blocked'])
    <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;background:#fdecea;color:#b3261e;">{{ __('common.collector_state_blocked') }}</span>
@elseif($status['warning'])
    <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;background:#fff4e5;color:#a15c00;">{{ __('common.collector_state_warning') }}</span>
@else
    <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:600;background:#e8f5e9;color:#1b5e20;">{{ __('common.collector_state_ok') }}</span>
@endif
