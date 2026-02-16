@extends('layouts.app')

@section('title', __('common.super_admin') . ' - ' . __('common.dashboard'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 20px; color: #4a9eff;">{{ __('common.super_admin') }} {{ __('common.dashboard') }}</h1>
    
    <p style="margin-bottom: 20px; color: #aaa;">Welcome, {{ auth()->user()->name }}!</p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 30px;">
        <div class="card" style="background: #3d3d3d;">
            <h3 style="color: #4a9eff; margin-bottom: 10px;">{{ __('common.business_units') }}</h3>
            <p style="font-size: 32px; font-weight: bold; color: #4a9eff;">{{ $businessUnits->count() }}</p>
            <a href="{{ route('superadmin.business-units.index') }}" class="btn" style="margin-top: 10px;">{{ __('common.view_all') }}</a>
        </div>
        
        <div class="card" style="background: #3d3d3d;">
            <h3 style="color: #4a9eff; margin-bottom: 10px;">{{ __('common.users') }}</h3>
            <p style="font-size: 32px; font-weight: bold; color: #4a9eff;">{{ \App\Models\User::count() }}</p>
        </div>
        
        <div class="card" style="background: #3d3d3d;">
            <h3 style="color: #4a9eff; margin-bottom: 10px;">{{ __('common.transaction_types') }}</h3>
            <p style="font-size: 32px; font-weight: bold; color: #4a9eff;">{{ $transactionTypesCount }}</p>
            <a href="{{ route('superadmin.transaction-types.index') }}" class="btn" style="margin-top: 10px;">{{ __('common.manage') }}</a>
        </div>
    </div>
    
    <div style="margin-top: 30px;">
        <a href="{{ route('superadmin.business-units.create') }}" class="btn">{{ __('common.create_business_unit') }}</a>
    </div>

    @if($businessUnits->count() > 0)
    <div class="card" style="margin-top: 30px;">
        <h2 style="margin-bottom: 20px; color: #4a9eff;">{{ __('common.recent_business_units') }}</h2>
        <div style="display: grid; gap: 15px;">
            @foreach($businessUnits->take(5) as $businessUnit)
                <div style="padding: 15px; background: #3d3d3d; border-radius: 4px; border: 1px solid #404040;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="color: #4a9eff; margin-bottom: 5px;">{{ $businessUnit->name }}</h3>
                            @if($businessUnit->email)
                                <p style="color: #aaa; font-size: 14px;">{{ $businessUnit->email }}</p>
                            @endif
                        </div>
                        <form action="{{ route('superadmin.switch-business', $businessUnit->id) }}" method="POST" style="display: inline;">
                            @csrf
                            <button type="submit" class="btn" style="padding: 8px 16px;">{{ __('common.switch_business') }}</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection

