@extends('layouts.app')

@section('title', __('common.login'))

@section('content')
<div style="max-width: 400px; margin: 100px auto;">
    <div class="card">
        <div style="text-align: center; margin-bottom: 24px;">
            <img src="{{ asset('images/sunu-logo.png') }}" alt="{{ config('app.name') }}" style="max-width: 240px; height: auto; background: #fff; border-radius: 8px;">
        </div>
        <h2 style="margin-bottom: 24px; color: var(--accent);">{{ __('common.login_to_account') }}</h2>
        
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
</div>
@endsection

