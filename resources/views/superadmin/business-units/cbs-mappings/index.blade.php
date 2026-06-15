@extends('layouts.app')

@section('title', __('common.cbs_mappings'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: #4a9eff;">{{ __('common.cbs_mappings') }} — {{ $businessUnit->name }}</h1>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('superadmin.business-units.cbs-mappings.create', $businessUnit->id) }}" class="btn">+ {{ __('common.add_cbs_mapping') }}</a>
            <a href="{{ route('superadmin.business-units.edit', $businessUnit->id) }}" class="btn btn-secondary">{{ __('common.back') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 12px; margin-bottom: 20px; background: #2d5a2d; color: #90ee90; border-radius: 4px;">{{ session('success') }}</div>
    @endif

    @if($mappings->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #404040;">
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.transaction_type') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.code') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.cbs_http_method') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.cbs_endpoint_path') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.status') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mappings as $mapping)
                        <tr style="border-bottom: 1px solid #404040;">
                            <td style="padding: 12px;">{{ $mapping->transactionType->name ?? '-' }}</td>
                            <td style="padding: 12px;"><code>{{ $mapping->transactionType->code ?? '-' }}</code></td>
                            <td style="padding: 12px;">{{ $mapping->http_method }}</td>
                            <td style="padding: 12px;">{{ $mapping->endpoint_path }}</td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $mapping->is_active ? '#2d5a2d' : '#5a2d2d' }}; color: {{ $mapping->is_active ? '#90ee90' : '#ee9090' }};">
                                    {{ $mapping->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('superadmin.business-units.cbs-mappings.edit', [$businessUnit->id, $mapping->id]) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                                    <form action="{{ route('superadmin.business-units.cbs-mappings.destroy', [$businessUnit->id, $mapping->id]) }}" method="POST" onsubmit="return confirm('{{ __('common.are_you_sure') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-secondary" style="padding: 6px 12px; font-size: 12px;">{{ __('common.delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: #888;">
            <p style="margin-bottom: 20px;">{{ __('common.no_cbs_mappings') }}</p>
            <a href="{{ route('superadmin.business-units.cbs-mappings.create', $businessUnit->id) }}" class="btn">{{ __('common.add_cbs_mapping') }}</a>
        </div>
    @endif
</div>
@endsection
