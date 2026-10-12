@extends('layouts.app')

@section('title', __('common.client_group'))
@section('page-title', __('common.client_group'))

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 15px;">
        <h1 class="text-accent">{{ $group->name }}</h1>
        <div class="btn-row">
            <a href="{{ route('clients.groups.edit', $group->id) }}" class="btn">{{ __('common.edit') }}</a>
            <form method="POST" action="{{ route('clients.groups.destroy', $group->id) }}" style="display: inline;" onsubmit="return confirm('{{ __('common.confirm_delete') }}');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn" style="background: var(--error-bg); color: var(--error-text);">{{ __('common.delete') }}</button>
            </form>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div>
            <label style="color: var(--text-muted); font-size: 14px;">{{ __('common.group_name') }}</label>
            <div style="padding: 10px; background: var(--bg-tertiary); border-radius: 4px; margin-top: 5px; color: var(--text-primary); font-weight: 600;">
                {{ $group->name }}
            </div>
        </div>

        @if($group->description)
        <div class="detail-full">
            <label style="color: var(--text-muted); font-size: 14px;">{{ __('common.description') }}</label>
            <div style="padding: 10px; background: var(--bg-tertiary); border-radius: 4px; margin-top: 5px; color: var(--text-primary);">
                {{ $group->description }}
            </div>
        </div>
        @endif

        <div>
            <label style="color: var(--text-muted); font-size: 14px;">{{ __('common.member_count') }}</label>
            <div style="padding: 10px; background: var(--bg-tertiary); border-radius: 4px; margin-top: 5px; color: var(--text-primary); font-weight: 600;">
                {{ $group->clients_count }} {{ __('common.members') }}
            </div>
        </div>
    </div>

    <div>
        <h2 style="color: var(--accent-color); margin-bottom: 20px;">{{ __('common.group_members') }}</h2>
        
        @if($group->clients->count() > 0)
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>{{ __('common.name') }}</th>
                            <th>{{ __('common.phone_number') }}</th>
                            <th>{{ __('common.email') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($group->clients as $client)
                            <tr>
                                <td>
                                    <a href="{{ route('clients.show', $client->id) }}" style="color: var(--accent-color); text-decoration: none;">
                                        {{ $client->full_name }}
                                    </a>
                                </td>
                                <td>{{ $client->phone_number ?? '-' }}</td>
                                <td>{{ $client->email ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="muted-center">
                <p>{{ __('common.no_members_in_group') }}</p>
                <a href="{{ route('clients.groups.edit', $group->id) }}" class="btn mt-20">{{ __('common.add_members') }}</a>
            </div>
        @endif
    </div>

    <div style="margin-top: 30px;">
        <a href="{{ route('clients.groups.index') }}" class="btn btn-secondary">{{ __('common.back_to_list') }}</a>
    </div>
</div>
@endsection
