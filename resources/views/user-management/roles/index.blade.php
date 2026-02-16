@extends('layouts.app')

@section('title', __('common.roles'))
@section('page-title', __('common.roles'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: #4a9eff;">{{ __('common.roles') }}</h1>
        <a href="{{ route('superadmin.user-management.roles.create') }}" class="btn">{{ __('common.create_role') }}</a>
    </div>

    @if($roles->count() > 0)
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('common.business_unit') }}</th>
                        <th>{{ __('common.description') }}</th>
                        <th>{{ __('common.permissions') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                        <tr>
                            <td>{{ $role->name }}</td>
                            <td>{{ $role->businessUnit ? $role->businessUnit->name : __('common.global') }}</td>
                            <td>{{ $role->description ?? '-' }}</td>
                            <td>{{ $role->permissions->count() }} {{ __('common.permissions') }}</td>
                            <td>
                                <a href="{{ route('superadmin.user-management.roles.edit', $role->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            {{ $roles->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: #888;">
            <p>{{ __('common.no_roles') }}</p>
            <a href="{{ route('superadmin.user-management.roles.create') }}" class="btn" style="margin-top: 20px;">{{ __('common.create_role') }}</a>
        </div>
    @endif
</div>
@endsection

