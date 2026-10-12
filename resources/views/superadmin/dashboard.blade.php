@extends('layouts.app')

@section('title', __('common.super_admin') . ' - ' . __('common.dashboard'))

@section('content')
<x-page-header :title="__('common.super_admin') . ' ' . __('common.dashboard')"
               :subtitle="__('common.welcome') . ', ' . auth()->user()->name . '!'">
    <a href="{{ route('superadmin.business-units.create') }}" class="btn">
        <x-icon name="plus" :size="16" /> {{ __('common.create_business_unit') }}
    </a>
</x-page-header>

<div class="stat-grid">
    <x-stat-card icon="building" :label="__('common.business_units')" :value="$businessUnits->count()"
                 :href="route('superadmin.business-units.index')" />
    <x-stat-card icon="users" :label="__('common.users')" :value="\App\Models\User::count()" />
    <x-stat-card icon="card" :label="__('common.transaction_types')" :value="$transactionTypesCount"
                 :href="route('superadmin.transaction-types.index')" />
</div>

@if($businessUnits->count() > 0)
<div class="card">
    <h2 class="mb-20">{{ __('common.recent_business_units') }}</h2>
    <div style="display: grid; gap: 12px;">
        @foreach($businessUnits->take(5) as $businessUnit)
            <div style="padding: 16px; background: var(--bg-tertiary); border-radius: var(--radius-sm); border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; gap: 16px;">
                <div>
                    <h3 style="margin-bottom: 4px;">{{ $businessUnit->name }}</h3>
                    @if($businessUnit->email)
                        <p style="color: var(--text-secondary); font-size: 14px;">{{ $businessUnit->email }}</p>
                    @endif
                </div>
                <form action="{{ route('superadmin.switch-business', $businessUnit->id) }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="btn btn-sm">{{ __('common.switch_business') }}</button>
                </form>
            </div>
        @endforeach
    </div>
</div>
@endif
@endsection
