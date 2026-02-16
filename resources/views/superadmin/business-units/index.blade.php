@extends('layouts.app')

@section('title', __('common.business_units'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: #4a9eff;">{{ __('common.business_units') }}</h1>
        <a href="{{ route('superadmin.business-units.create') }}" class="btn">{{ __('common.create_business_unit') }}</a>
    </div>

    @if($businessUnits->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #404040;">
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.logo') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.name') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.email') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.phone') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.users') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.kato_status') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.status') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($businessUnits as $businessUnit)
                        <tr style="border-bottom: 1px solid #404040;">
                            <td style="padding: 12px;">
                                @if($businessUnit->logo)
                                    <img src="{{ asset('storage/' . $businessUnit->logo) }}" alt="{{ $businessUnit->name }}" class="business-logo-small">
                                @else
                                    <span style="color: var(--text-muted);">-</span>
                                @endif
                            </td>
                            <td style="padding: 12px;">{{ $businessUnit->name }}</td>
                            <td style="padding: 12px;">{{ $businessUnit->email ?? '-' }}</td>
                            <td style="padding: 12px;">{{ $businessUnit->phone ?? '-' }}</td>
                            <td style="padding: 12px;">{{ $businessUnit->users_count }}</td>
                            <td style="padding: 12px;">
                                @php
                                    $isKato = $businessUnit->coreBankingSystem && strtolower($businessUnit->coreBankingSystem->code) === 'kato';
                                @endphp
                                @if($isKato)
                                    @if($businessUnit->kato_registered_at)
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #2d5a2d; color: #90ee90;" title="{{ __('common.kato_registered') }}">
                                            {{ __('common.registered') }}
                                        </span>
                                    @elseif($businessUnit->kato_registration_error)
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #5a2d2d; color: #ee9090;" title="{{ $businessUnit->kato_registration_error }}">
                                            {{ __('common.failed') }}
                                        </span>
                                    @else
                                        <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #5a5a2d; color: #eeee90;">
                                            {{ __('common.pending') }}
                                        </span>
                                    @endif
                                @else
                                    <span style="color: var(--text-muted);">-</span>
                                @endif
                            </td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $businessUnit->is_active ? '#2d5a2d' : '#5a2d2d' }}; color: {{ $businessUnit->is_active ? '#90ee90' : '#ee9090' }};">
                                    {{ $businessUnit->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td style="padding: 12px;">
                                <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                    <a href="{{ route('superadmin.business-units.edit', $businessUnit->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                                    <a href="{{ route('superadmin.business-units.users', $businessUnit->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.users') }}</a>
                                    @if($isKato && !$businessUnit->kato_registered_at && $businessUnit->coreBankingSystem->base_url)
                                        <form action="{{ route('superadmin.business-units.register-kato', $businessUnit->id) }}" method="POST" style="display: inline;">
                                            @csrf
                                            <button type="submit" class="btn" style="padding: 6px 12px; font-size: 12px; background: #4a9eff;">{{ __('common.register_with_kato') }}</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('superadmin.switch-business', $businessUnit->id) }}" method="POST" style="display: inline;">
                                        @csrf
                                        <button type="submit" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.switch_business') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            {{ $businessUnits->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: #888;">
            <p style="margin-bottom: 20px;">{{ __('common.no_business_units') }}</p>
            <a href="{{ route('superadmin.business-units.create') }}" class="btn">{{ __('common.create_business_unit') }}</a>
        </div>
    @endif
</div>
@endsection

