@extends('layouts.app')

@section('title', __('common.business_units'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.business_units') }}</h1>
        <a href="{{ route('superadmin.business-units.create') }}" class="btn">{{ __('common.create_business_unit') }}</a>
    </div>

    @if($businessUnits->count() > 0)
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th>{{ __('common.logo') }}</th>
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('common.email') }}</th>
                        <th>{{ __('common.phone') }}</th>
                        <th>{{ __('common.users') }}</th>
                        <th>{{ __('common.kato_status') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($businessUnits as $businessUnit)
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>
                                @if($businessUnit->logo)
                                    <img src="{{ asset('storage/' . $businessUnit->logo) }}" alt="{{ $businessUnit->name }}" class="business-logo-small">
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                            <td>{{ $businessUnit->name }}</td>
                            <td>{{ $businessUnit->email ?? '-' }}</td>
                            <td>{{ $businessUnit->phone ?? '-' }}</td>
                            <td>{{ $businessUnit->users_count }}</td>
                            <td>
                                @php
                                    $isKato = $businessUnit->coreBankingSystem && strtolower($businessUnit->coreBankingSystem->code) === 'kato';
                                @endphp
                                @if($isKato)
                                    @if($businessUnit->kato_registered_at)
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--success-bg); color: var(--success-text);" title="{{ __('common.kato_registered') }}">
                                            {{ __('common.registered') }}
                                        </span>
                                    @elseif($businessUnit->kato_registration_error)
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--error-bg); color: var(--error-text);" title="{{ $businessUnit->kato_registration_error }}">
                                            {{ __('common.failed') }}
                                        </span>
                                    @else
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--warning-bg); color: var(--warning-text);">
                                            {{ __('common.pending') }}
                                        </span>
                                    @endif
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $businessUnit->is_active ? 'var(--success-bg)' : 'var(--error-bg)' }}; color: {{ $businessUnit->is_active ? 'var(--success-text)' : 'var(--error-text)' }};">
                                    {{ $businessUnit->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <a href="{{ route('superadmin.business-units.edit', $businessUnit->id) }}" class="btn btn-sm">{{ __('common.edit') }}</a>
                                    <a href="{{ route('superadmin.business-units.users', $businessUnit->id) }}" class="btn btn-sm">{{ __('common.users') }}</a>
                                    @if($isKato && !$businessUnit->kato_registered_at && $businessUnit->coreBankingSystem->base_url)
                                        <form action="{{ route('superadmin.business-units.register-kato', $businessUnit->id) }}" method="POST" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn" style="padding: 6px 12px; font-size: 12px; background: var(--accent);">{{ __('common.register_with_kato') }}</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('superadmin.switch-business', $businessUnit->id) }}" method="POST" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-sm">{{ __('common.switch_business') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-20">
            {{ $businessUnits->links() }}
        </div>
    @else
        <div class="muted-center">
            <p class="mb-20">{{ __('common.no_business_units') }}</p>
            <a href="{{ route('superadmin.business-units.create') }}" class="btn">{{ __('common.create_business_unit') }}</a>
        </div>
    @endif
</div>
@endsection

