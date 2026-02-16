@extends('layouts.app')

@section('title', __('common.users') . ' - ' . $businessUnit->name)

@section('content')
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div>
            <h1 style="color: #4a9eff; margin-bottom: 5px;">{{ __('common.users') }} - {{ $businessUnit->name }}</h1>
            <a href="{{ route('superadmin.business-units.index') }}" class="btn btn-secondary" style="margin-top: 10px;">{{ __('common.back') }}</a>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('superadmin.business-units.create-role', $businessUnit->id) }}" class="btn">{{ __('common.create_role') }}</a>
        </div>
    </div>

    <!-- Add User Form -->
    <div class="card" style="background: #3d3d3d; margin-bottom: 30px;">
        <h2 style="color: #4a9eff; margin-bottom: 20px;">{{ __('common.add_user') }}</h2>
        <form method="POST" action="{{ route('superadmin.business-units.add-user', $businessUnit->id) }}">
            @csrf
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <div class="form-group">
                    <label class="form-label" for="name">{{ __('common.user_name') }} <span style="color: #ff6b6b;">*</span></label>
                    <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required>
                    @error('name')
                        <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">{{ __('common.user_email') }} <span style="color: #ff6b6b;">*</span></label>
                    <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    @error('email')
                        <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">{{ __('common.password') }} <span style="color: #ff6b6b;">*</span></label>
                    <input type="password" id="password" name="password" class="form-control" required minlength="8">
                    @error('password')
                        <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
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
                        <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div style="margin-top: 20px;">
                <button type="submit" class="btn">{{ __('common.add_user') }}</button>
            </div>
        </form>
    </div>

    <!-- Users List -->
    <h2 style="color: #4a9eff; margin-bottom: 20px;">{{ __('common.users') }} ({{ $businessUnit->users->count() }})</h2>
    
    @if($businessUnit->users->count() > 0)
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #404040;">
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.name') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.email') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.role') }}</th>
                        <th style="padding: 12px; text-align: left; color: #4a9eff;">{{ __('common.status') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($businessUnit->users as $user)
                        @php
                            $pivot = $user->pivot;
                            $role = $pivot->role_id ? \App\Models\Role::find($pivot->role_id) : null;
                        @endphp
                        <tr style="border-bottom: 1px solid #404040;">
                            <td style="padding: 12px;">{{ $user->name }}</td>
                            <td style="padding: 12px;">{{ $user->email }}</td>
                            <td style="padding: 12px;">{{ $role ? $role->name : '-' }}</td>
                            <td style="padding: 12px;">
                                <span style="padding: 4px 8px; border-radius: 4px; font-size: 12px; background: {{ $pivot->is_active ? '#2d5a2d' : '#5a2d2d' }}; color: {{ $pivot->is_active ? '#90ee90' : '#ee9090' }};">
                                    {{ $pivot->is_active ? __('common.active') : __('common.inactive') }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div style="text-align: center; padding: 40px; color: #888;">
            <p>{{ __('common.no_users') }}</p>
        </div>
    @endif
</div>
@endsection

