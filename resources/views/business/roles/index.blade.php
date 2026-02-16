@extends('layouts.app')

@section('title', __('common.roles'))
@section('page-title', __('common.roles') . ' - ' . $businessUnit->name)

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: #4a9eff;">{{ __('common.roles') }} - {{ $businessUnit->name }}</h1>
        <a href="{{ route('business.roles.create') }}" class="btn">{{ __('common.create_role') }}</a>
    </div>

    @if($roles->count() > 0)
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('common.description') }}</th>
                        <th>{{ __('common.permissions') }}</th>
                        <th>{{ __('common.users') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr>
                            <td><strong style="color: #4a9eff;">{{ $role->name }}</strong></td>
                            <td>{{ $role->description ?? '-' }}</td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #3d3d3d; color: #4a9eff;">
                                    {{ $role->permissions->count() }} {{ __('common.permissions') }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $usersCount = $businessUnit->users()->wherePivot('role_id', $role->id)->count();
                                @endphp
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #3d3d3d; color: #4a9eff;">
                                    {{ $usersCount }} {{ __('common.users') }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('business.roles.edit', $role->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: #888;">
            <p>{{ __('common.no_roles') }}</p>
            <a href="{{ route('business.roles.create') }}" class="btn" style="margin-top: 20px;">{{ __('common.create_role') }}</a>
        </div>
    @endif
</div>
@endsection

