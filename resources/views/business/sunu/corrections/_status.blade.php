@php
    $corrColors = ['pending' => '#e67e22', 'approved' => '#2980b9', 'rejected' => '#c0392b', 'done' => '#27ae60'];
@endphp
<span class="badge" style="background:{{ $corrColors[$status] ?? '#7f8c8d' }}; color:#fff;">{{ __('common.sunu_corr_status_'.$status) }}</span>
