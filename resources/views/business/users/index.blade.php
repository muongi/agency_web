@extends('layouts.app')

@section('title', __('common.users'))
@section('page-title', __('common.users') . ' - ' . $businessUnit->name)

@section('content')
<div class="card">
    <div class="toolbar">
        <h1 class="text-accent">{{ __('common.users') }} - {{ $businessUnit->name }}</h1>
        <a href="{{ route('business.users.create') }}" class="btn">{{ __('common.add_user') }}</a>
    </div>

    @if($users->count() > 0)
        <div class="data-table">
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
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $pivot->is_active ? 'var(--success-bg)' : 'var(--error-bg)' }}; color: {{ $pivot->is_active ? 'var(--success-text)' : 'var(--error-text)' }};">
                                    {{ $pivot->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('business.users.edit', $user->id) }}" class="btn btn-sm">{{ __('common.edit') }}</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="muted-center">
            <p>{{ __('common.no_users') }}</p>
            <a href="{{ route('business.users.create') }}" class="btn mt-20">{{ __('common.add_user') }}</a>
        </div>
    @endif
</div>
@endsection

