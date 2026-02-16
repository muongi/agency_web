@extends('layouts.app')

@section('title', __('common.users'))
@section('page-title', __('common.users'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <h1 style="color: #4a9eff;">{{ __('common.users') }}</h1>
        <a href="{{ route('superadmin.user-management.users.create') }}" class="btn">{{ __('common.add_user') }}</a>
    </div>

    @if($users->count() > 0)
        <div style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('common.email') }}</th>
                        <th>{{ __('common.business_unit') }}</th>
                        <th>{{ __('common.role') }}</th>
                        <th>{{ __('common.status') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        @php
                            $businessUnit = $user->currentBusinessUnit;
                            $role = $businessUnit ? $user->getRoleInBusiness($businessUnit->id) : null;
                        @endphp
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $businessUnit ? $businessUnit->name : '-' }}</td>
                            <td>{{ $role ? $role->name : '-' }}</td>
                            <td>
                                @if($user->is_super_admin)
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #4a9eff; color: white;">Super Admin</span>
                                @else
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: #2d5a2d; color: #90ee90;">Active</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('superadmin.user-management.users.edit', $user->id) }}" class="btn" style="padding: 6px 12px; font-size: 12px;">{{ __('common.edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top: 20px;">
            {{ $users->links() }}
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: #888;">
            <p>{{ __('common.no_users') }}</p>
            <a href="{{ route('superadmin.user-management.users.create') }}" class="btn" style="margin-top: 20px;">{{ __('common.add_user') }}</a>
        </div>
    @endif
</div>
@endsection

