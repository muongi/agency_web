@extends('layouts.app')

@section('title', __('common.login'))

@section('content')
<div style="max-width: 400px; margin: 100px auto;">
    <div class="card">
        <h2 style="margin-bottom: 24px; color: #4a9eff;">{{ __('common.login_to_account') }}</h2>
        
        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div class="form-group">
                <label class="form-label" for="email">{{ __('common.email_address') }}</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    class="form-control" 
                    value="{{ old('email') }}" 
                    required 
                    autofocus
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label class="form-label" for="password">{{ __('common.password') }}</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    class="form-control" 
                    required
                    autocomplete="current-password"
                >
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" name="remember" id="remember">
                    <label for="remember" style="margin: 0;">{{ __('common.remember_me') }}</label>
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn" style="width: 100%;">
                    {{ __('common.login') }}
                </button>
            </div>
        </form>
        
        <div style="margin-top: 20px; text-align: center;">
            @include('components.language-switcher')
        </div>
    </div>
    
    <div style="text-align: center; margin-top: 20px; color: #888;">
        <p>Super Admin Credentials:</p>
        <p>Email: muongi@agency.com</p>
        <p>Password: 12345</p>
    </div>
</div>
@endsection

