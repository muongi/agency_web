@extends('layouts.app')

@section('title', __('common.add_agent'))
@section('page-title', __('common.add_agent'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.add_agent') }}</h1>
    
    <form method="POST" action="{{ route('agents.store') }}" enctype="multipart/form-data">
        @csrf

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="first_name">{{ __('common.first_name') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="first_name" name="first_name" class="form-control" value="{{ old('first_name') }}" required>
                @error('first_name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="second_name">{{ __('common.second_name') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="second_name" name="second_name" class="form-control" value="{{ old('second_name') }}" required>
                @error('second_name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="photo">{{ __('common.photo') }}</label>
                <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                @error('photo')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="email">{{ __('common.email') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required>
                @error('email')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="phone_number">{{ __('common.phone_number') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="phone_number" name="phone_number" class="form-control" value="{{ old('phone_number') }}" required>
                @error('phone_number')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="id_passport">{{ __('common.id_passport') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="id_passport" name="id_passport" class="form-control" value="{{ old('id_passport') }}" required>
                @error('id_passport')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="physical_address">{{ __('common.physical_address') }}</label>
                <textarea id="physical_address" name="physical_address" class="form-control" rows="3">{{ old('physical_address') }}</textarea>
                @error('physical_address')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="po_box">{{ __('common.po_box') }}</label>
                <input type="text" id="po_box" name="po_box" class="form-control" value="{{ old('po_box') }}">
                @error('po_box')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="username">{{ __('common.username') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="username" name="username" class="form-control" value="{{ old('username') }}" required>
                @error('username')
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
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('agents.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

