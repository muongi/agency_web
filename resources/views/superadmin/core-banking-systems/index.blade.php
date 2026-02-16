@extends('layouts.app')

@section('title', __('common.core_banking_systems'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: #4a9eff;">{{ __('common.core_banking_systems') }}</h1>
        <a href="{{ route('superadmin.core-banking-systems.create') }}" class="btn">{{ __('common.create_cbs') }}</a>
    </div>

    @if(session('success'))
        <div style="padding: 12px; margin-bottom: 20px; background: #2d5a2d; color: #90ee90; border-radius: 4px;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="padding: 12px; margin-bottom: 20px; background: #5a2d2d; color: #ee9090; border-radius: 4px;">
            {{ session('error') }}
        </div>
    @endif

    @if($cbs->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #404040;">
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.cbs_name') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.cbs_code') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.cbs_description') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.kato_base_url') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.business_units') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.status') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cbs as $cbsItem)
                        <tr style="border-bottom: 1px solid #404040;">
                            <td style="padding: 12px;">{{ $cbsItem->name }}</td>
                            <td style="padding: 12px;">{{ $cbsItem->code ?? '-' }}</td>
                            <td style="padding: 12px;">{{ $cbsItem->description ? \Illuminate\Support\Str::limit($cbsItem->description, 50) : '-' }}</td>
                            <td style="padding: 12px;">{{ $cbsItem->base_url ? \Illuminate\Support\Str::limit($cbsItem->base_url, 40) : '-' }}</td>
                            <td style="padding: 12px;">{{ $cbsItem->business_units_count }}</td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $cbsItem->is_active ? '#2d5a2d' : '#5a2d2d' }}; color: {{ $cbsItem->is_active ? '#90ee90' : '#ee9090' }};">
                                    {{ $cbsItem->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('superadmin.core-banking-systems.edit', $cbsItem->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                                    <form action="{{ route('superadmin.core-banking-systems.destroy', $cbsItem->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.are_you_sure') }}');">
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

        <div style="margin-top: 20px;">
            {{ $cbs->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: #888;">
            <p style="margin-bottom: 20px;">{{ __('common.no_cbs_found') }}</p>
            <a href="{{ route('superadmin.core-banking-systems.create') }}" class="btn">{{ __('common.create_cbs') }}</a>
        </div>
    @endif
</div>
@endsection