@extends('layouts.app')

@section('title', __('common.users'))
@section('page-title', __('common.users'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.users') }}</h1>
        <a href="{{ route('superadmin.user-management.users.create') }}" class="btn">{{ __('common.add_user') }}</a>
    </div>

    @if($users->count() > 0)
        <div class="data-table">
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
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--accent); color: white;">{{ __('common.super_admin') }}</span>
                                @else
                                    <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--success-bg); color: var(--success-text);">{{ __('common.active') }}</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('superadmin.user-management.users.edit', $user->id) }}" class="btn btn-sm">{{ __('common.edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-20">
            {{ $users->links() }}
        </div>
    @else
        <div class="muted-center">
            <p>{{ __('common.no_users') }}</p>
            <a href="{{ route('superadmin.user-management.users.create') }}" class="btn mt-20">{{ __('common.add_user') }}</a>
        </div>
    @endif
</div>
@endsection

