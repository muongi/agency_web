@extends('layouts.app')

@section('title', __('common.core_banking_systems'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.core_banking_systems') }}</h1>
        <a href="{{ route('superadmin.core-banking-systems.create') }}" class="btn">{{ __('common.create_cbs') }}</a>
    </div>

    @if(session('success'))
        <div style="padding: 12px; margin-bottom: 20px; background: var(--success-bg); color: var(--success-text); border-radius: 4px;">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div style="padding: 12px; margin-bottom: 20px; background: var(--error-bg); color: var(--error-text); border-radius: 4px;">
            {{ session('error') }}
        </div>
    @endif

    @if($cbs->count() > 0)
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th>{{ __('common.cbs_name') }}</th>
                        <th>{{ __('common.cbs_code') }}</th>
                        <th>{{ __('common.cbs_description') }}</th>
                        <th>{{ __('common.kato_base_url') }}</th>
                        <th>{{ __('common.business_units') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($cbs as $cbsItem)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>{{ $cbsItem->name }}</td>
                            <td>{{ $cbsItem->code ?? '-' }}</td>
                            <td>{{ $cbsItem->description ? \Illuminate\Support\Str::limit($cbsItem->description, 50) : '-' }}</td>
                            <td>{{ $cbsItem->base_url ? \Illuminate\Support\Str::limit($cbsItem->base_url, 40) : '-' }}</td>
                            <td>{{ $cbsItem->business_units_count }}</td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $cbsItem->is_active ? 'var(--success-bg)' : 'var(--error-bg)' }}; color: {{ $cbsItem->is_active ? 'var(--success-text)' : 'var(--error-text)' }};">
                                    {{ $cbsItem->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('superadmin.core-banking-systems.edit', $cbsItem->id) }}" class="btn btn-sm">{{ __('common.edit') }}</a>
                                    <form action="{{ route('superadmin.core-banking-systems.destroy', $cbsItem->id) }}" method="POST" style="display: inline;" onsubmit="return confirm('{{ __('common.are_you_sure') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-secondary btn-sm">{{ __('common.delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-20">
            {{ $cbs->links() }}
        </div>
    @else
        <div class="muted-center">
            <p class="mb-20">{{ __('common.no_cbs_found') }}</p>
            <a href="{{ route('superadmin.core-banking-systems.create') }}" class="btn">{{ __('common.create_cbs') }}</a>
        </div>
    @endif
</div>
@endsection