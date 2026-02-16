@extends('layouts.app')

@section('title', __('common.users'))
@section('page-title', __('common.users') . ' - ' . $businessUnit->name)

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: #4a9eff;">{{ __('common.users') }} - {{ $businessUnit->name }}</h1>
        <a href="{{ route('business.users.create') }}" class="btn">{{ __('common.add_user') }}</a>
    </div>

    @if($users->count() > 0)
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('common.email') }}</th>
                        <th>{{ __('common.role') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        @php
                            $pivot = $user->pivot;
                            $role = $pivot->role_id ? \App\Models\Role::find($pivot->role_id) : null;
                        @endphp
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $role ? $role->name : '-' }}</td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $pivot->is_active ? '#2d5a2d' : '#5a2d2d' }}; color: {{ $pivot->is_active ? '#90ee90' : '#ee9090' }};">
                                    {{ $pivot->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('business.users.edit', $user->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: #888;">
            <p>{{ __('common.no_users') }}</p>
            <a href="{{ route('business.users.create') }}" class="btn" style="margin-top: 20px;">{{ __('common.add_user') }}</a>
        </div>
    @endif
</div>
@endsection

