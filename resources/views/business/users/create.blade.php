@extends('layouts.app')

@section('title', __('common.add_user'))
@section('page-title', __('common.add_user') . ' - ' . $businessUnit->name)

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: #4a9eff;">{{ __('common.add_user') }} - {{ $businessUnit->name }}</h1>
    
    <form method="POST" action="{{ route('business.users.store') }}">
        @csrf

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
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
                        <option value="{{ $role->id }}" {{ old('role_id') == $role->id ? 'selected' : '' }}>
                            {{ $role->name }}
                        </option>
                    @endforeach
                </select>
                @error('role_id')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="preferred_language">{{ __('common.preferred_language') }}</label>
                <select id="preferred_language" name="preferred_language" class="form-select">
                    <option value="en" {{ old('preferred_language', 'en') == 'en' ? 'selected' : '' }}>English</option>
                    <option value="fr" {{ old('preferred_language') == 'fr' ? 'selected' : '' }}>Français</option>
                    <option value="pt" {{ old('preferred_language') == 'pt' ? 'selected' : '' }}>Português</option>
                </select>
                @error('preferred_language')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 24px;">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('business.users.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

