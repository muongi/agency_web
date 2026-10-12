@extends('layouts.app')

@section('title', __('common.users') . ' - ' . $businessUnit->name)

@section('content')
<div class="card">
    <div class="toolbar">
        <div>
            <h1 style="color: var(--accent); margin-bottom: 5px;">{{ __('common.users') }} - {{ $businessUnit->name }}</h1>
            <a href="{{ route('superadmin.business-units.index') }}" class="btn btn-secondary" style="margin-top: 10px;">{{ __('common.back') }}</a>
        </div>
        <div class="btn-row">
            <a href="{{ route('superadmin.business-units.create-role', $businessUnit->id) }}" class="btn">{{ __('common.create_role') }}</a>
        </div>
    </div>

    <!-- Add User Form -->
    <div class="card" style="background: var(--bg-tertiary); margin-bottom: 30px;">
        <h2 style="color: var(--accent); margin-bottom: 20px;">{{ __('common.add_user') }}</h2>
        <form method="POST" action="{{ route('superadmin.business-units.add-user', $businessUnit->id) }}">
            @csrf
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label class="form-label" for="name">{{ __('common.user_name') }} <span class="text-error">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required>
                    @error('name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">{{ __('common.user_email') }} <span class="text-error">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    @error('email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">{{ __('common.password') }} <span class="text-error">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" required minlength="8">
                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="role_id">{{ __('common.role') }}</label>
                    <select id="role_id" name="role_id" class="form-select">
                        <option value="">{{ __('common.select_role') }}</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>{{ $role->name }}</option>
                        @endforeach
                    </select>
                    @error('role_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="mt-20">
                <button type="submit" class="btn">{{ __('common.add_user') }}</button>
            </div>
        </form>
    </div>

    <!-- Users List -->
    <h2 style="color: var(--accent); margin-bottom: 20px;">{{ __('common.users') }} ({{ $businessUnit->users->count() }})</h2>
    
    @if($businessUnit->users->count() > 0)
        <div class="data-table">
            <table>
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th>{{ __('common.name') }}</th>
                        <th>{{ __('common.email') }}</th>
                        <th>{{ __('common.role') }}</th>
                        <th>{{ __('common.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($businessUnit->users as $user)
                        @php
                            $pivot = $user->pivot;
                            $role = $pivot->role_id ? \App\Models\Role::find($pivot->role_id) : null;
                        @endphp
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>{{ $role ? $role->name : '-' }}</td>
                            <td>
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $pivot->is_active ? 'var(--success-bg)' : 'var(--error-bg)' }}; color: {{ $pivot->is_active ? 'var(--success-text)' : 'var(--error-text)' }};">
                                    {{ $pivot->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="muted-center">
            <p>{{ __('common.no_users') }}</p>
        </div>
    @endif
</div>
@endsection

