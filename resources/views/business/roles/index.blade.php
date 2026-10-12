@extends('layouts.app')

@section('title', __('common.roles'))
@section('page-title', __('common.roles') . ' - ' . $businessUnit->name)

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.roles') }} - {{ $businessUnit->name }}</h1>
        <a href="{{ route('business.roles.create') }}" class="btn">{{ __('common.create_role') }}</a>
    </div>

    @if($roles->count() > 0)
        <div class="data-table">
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
                            <td><strong class="text-accent">{{ $role->name }}</strong></td>
                            <td>{{ $role->description ?? '-' }}</td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--bg-tertiary); color: var(--accent);">
                                    {{ $role->permissions->count() }} {{ __('common.permissions') }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $usersCount = $businessUnit->users()->wherePivot('role_id', $role->id)->count();
                                @endphp
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--bg-tertiary); color: var(--accent);">
                                    {{ $usersCount }} {{ __('common.users') }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('business.roles.edit', $role->id) }}" class="btn btn-sm">{{ __('common.edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="muted-center">
            <p>{{ __('common.no_roles') }}</p>
            <a href="{{ route('business.roles.create') }}" class="btn mt-20">{{ __('common.create_role') }}</a>
        </div>
    @endif
</div>
@endsection

