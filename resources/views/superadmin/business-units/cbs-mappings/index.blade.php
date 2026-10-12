@extends('layouts.app')

@section('title', __('common.cbs_mappings'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.cbs_mappings') }} — {{ $businessUnit->name }}</h1>
        <div class="btn-row">
            <a href="{{ route('superadmin.business-units.cbs-mappings.create', $businessUnit->id) }}" class="btn">+ {{ __('common.add_cbs_mapping') }}</a>
            <a href="{{ route('superadmin.business-units.edit', $businessUnit->id) }}" class="btn btn-secondary">{{ __('common.back') }}</a>
        </div>
    </div>

    @if(session('success'))
        <div style="padding: 12px; margin-bottom: 20px; background: var(--success-bg); color: var(--success-text); border-radius: 4px;">{{ session('success') }}</div>
    @endif

    @if($mappings->count() > 0)
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th>{{ __('common.transaction_type') }}</th>
                        <th>{{ __('common.code') }}</th>
                        <th>{{ __('common.cbs_http_method') }}</th>
                        <th>{{ __('common.cbs_endpoint_path') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mappings as $mapping)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>{{ $mapping->transactionType->name ?? '-' }}</td>
                            <td><code>{{ $mapping->transactionType->code ?? '-' }}</code></td>
                            <td>{{ $mapping->http_method }}</td>
                            <td>{{ $mapping->endpoint_path }}</td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $mapping->is_active ? 'var(--success-bg)' : 'var(--error-bg)' }}; color: {{ $mapping->is_active ? 'var(--success-text)' : 'var(--error-text)' }};">
                                    {{ $mapping->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="{{ route('superadmin.business-units.cbs-mappings.edit', [$businessUnit->id, $mapping->id]) }}" class="btn btn-sm">{{ __('common.edit') }}</a>
                                    <form action="{{ route('superadmin.business-units.cbs-mappings.destroy', [$businessUnit->id, $mapping->id]) }}" method="POST" onsubmit="return confirm('{{ __('common.are_you_sure') }}');">
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
    @else
        <div class="muted-center">
            <p class="mb-20">{{ __('common.no_cbs_mappings') }}</p>
            <a href="{{ route('superadmin.business-units.cbs-mappings.create', $businessUnit->id) }}" class="btn">{{ __('common.add_cbs_mapping') }}</a>
        </div>
    @endif
</div>
@endsection
