@extends('layouts.app')

@section('title', __('common.client_groups'))
@section('page-title', __('common.client_groups'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 class="text-accent">{{ __('common.client_groups') }}</h1>
        <a href="{{ route('clients.groups.create') }}" class="btn">{{ __('common.create_client_group') }}</a>
    </div>

    @if($groups->count() > 0)
        <div class="data-table">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('common.group_name') }}</th>
                        <th>{{ __('common.description') }}</th>
                        <th>{{ __('common.member_count') }}</th>
                        <th>{{ __('common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groups as $group)
                        <tr>
                            <td><strong class="text-accent">{{ $group->name }}</strong></td>
                            <td>{{ $group->description ?? '-' }}</td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: var(--bg-tertiary); color: var(--accent-color);">
                                    {{ $group->clients_count }} {{ __('common.members') }}
                                </span>
                            </td>
                            <td>
                                <div class="btn-row">
                                    <a href="{{ route('clients.groups.show', $group->id) }}" class="btn btn-sm">{{ __('common.view') }}</a>
                                    <a href="{{ route('clients.groups.edit', $group->id) }}" class="btn btn-sm">{{ __('common.edit') }}</a>
                                    <form method="POST" action="{{ route('clients.groups.destroy', $group->id) }}" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn" style="padding: 6px 12px; font-size: 12px; background: var(--error-bg); color: var(--error-text);">{{ __('common.delete') }}</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="muted-center">
            <p>{{ __('common.no_groups_available') }}</p>
            <a href="{{ route('clients.groups.create') }}" class="btn mt-20">{{ __('common.create_client_group') }}</a>
        </div>
    @endif
</div>
@endsection
