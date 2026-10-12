@extends('layouts.app')

@section('title', __('common.roles'))
@section('page-title', __('common.roles'))

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.roles') }}</h1>
        <a href="{{ route('superadmin.user-management.roles.create') }}" class="btn">{{ __('common.create_role') }}</a>
    </div>

    @if($roles->count() > 0)
        <div class="data-table">
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
                                <a href="{{ route('superadmin.user-management.roles.edit', $role->id) }}" class="btn btn-sm">{{ __('common.edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-20">
            {{ $roles->links() }}
        </div>
    @else
        <div class="muted-center">
            <p>{{ __('common.no_roles') }}</p>
            <a href="{{ route('superadmin.user-management.roles.create') }}" class="btn mt-20">{{ __('common.create_role') }}</a>
        </div>
    @endif
</div>
@endsection

