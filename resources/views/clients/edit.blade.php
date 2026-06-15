@extends('layouts.app')

@section('title', __('common.edit') . ' ' . __('common.client'))
@section('page-title', __('common.edit') . ' ' . __('common.client'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.edit') }} {{ __('common.client') }}</h1>
    
    <form method="POST" action="{{ route('clients.update', $client->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <!-- Personal Information -->
        <h2 style="color: var(--accent-color); margin-bottom: 20px; margin-top: 30px;">{{ __('common.personal_information') }}</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="salutation">{{ __('common.salutation') }}</label>
                <select id="salutation" name="salutation" class="form-select">
                    <option value="">{{ __('common.select_salutation') }}</option>
                    <option value="Mr" {{ old('salutation', $client->salutation) == 'Mr' ? 'selected' : '' }}>Mr</option>
                    <option value="Mrs" {{ old('salutation', $client->salutation) == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                    <option value="Ms" {{ old('salutation', $client->salutation) == 'Ms' ? 'selected' : '' }}>Ms</option>
                    <option value="Dr" {{ old('salutation', $client->salutation) == 'Dr' ? 'selected' : '' }}>Dr</option>
                </select>
                @error('salutation')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="first_name">{{ __('common.first_name') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="first_name" name="first_name" class="form-control" value="{{ old('first_name', $client->first_name) }}" required>
                @error('first_name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="middle_name">{{ __('common.middle_name') }}</label>
                <input type="text" id="middle_name" name="middle_name" class="form-control" value="{{ old('middle_name', $client->middle_name) }}">
                @error('middle_name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="last_name">{{ __('common.last_name') }} <span style="color: #ff6b6b;">*</span></label>
                <input type="text" id="last_name" name="last_name" class="form-control" value="{{ old('last_name', $client->last_name) }}" required>
                @error('last_name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="email">{{ __('common.email') }}</label>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $client->email) }}">
                @error('email')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="id_passport">{{ __('common.id_passport') }}</label>
                <input type="text" id="id_passport" name="id_passport" class="form-control" value="{{ old('id_passport', $client->id_passport) }}">
                @error('id_passport')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="home_address">{{ __('common.home_address') }}</label>
                <textarea id="home_address" name="home_address" class="form-control" rows="3">{{ old('home_address', $client->home_address) }}</textarea>
                @error('home_address')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="phone_number">{{ __('common.phone_number') }}</label>
                <input type="text" id="phone_number" name="phone_number" class="form-control" value="{{ old('phone_number', $client->phone_number) }}">
                @error('phone_number')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="photo">{{ __('common.photo') }}</label>
                @if($client->photo)
                    <div style="margin-bottom: 10px;">
                        <img src="{{ asset('storage/' . $client->photo) }}" alt="{{ $client->full_name }}" style="max-width: 150px; max-height: 150px; border-radius: 4px; border: 1px solid var(--border-color);">
                        <p style="color: var(--text-muted); font-size: 12px; margin-top: 5px;">{{ __('common.current_photo') }}</p>
                    </div>
                @endif
                <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                @error('photo')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="gender">
                    {{ __('common.gender') }}
                    @if(!empty($isKato))
                        <span style="color: #ff6b6b;">*</span>
                    @endif
                </label>
                <select id="gender" name="gender" class="form-select" @if(!empty($isKato)) required @endif>
                    <option value="">{{ __('common.select_gender') }}</option>
                    <option value="Male" {{ old('gender', $client->gender) == 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ old('gender', $client->gender) == 'Female' ? 'selected' : '' }}>Female</option>
                    @if(empty($isKato))
                        <option value="Other" {{ old('gender', $client->gender) == 'Other' ? 'selected' : '' }}>Other</option>
                    @endif
                </select>
                @if(!empty($isKato))
                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">{{ __('common.kato_gender_required_note') }}</small>
                @endif
                @error('gender')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="marital_status">{{ __('common.marital_status') }}</label>
                <select id="marital_status" name="marital_status" class="form-select">
                    <option value="">{{ __('common.select_marital_status') }}</option>
                    <option value="Single" {{ old('marital_status', $client->marital_status) == 'Single' ? 'selected' : '' }}>Single</option>
                    <option value="Married" {{ old('marital_status', $client->marital_status) == 'Married' ? 'selected' : '' }}>Married</option>
                    <option value="Divorced" {{ old('marital_status', $client->marital_status) == 'Divorced' ? 'selected' : '' }}>Divorced</option>
                    <option value="Widowed" {{ old('marital_status', $client->marital_status) == 'Widowed' ? 'selected' : '' }}>Widowed</option>
                </select>
                @error('marital_status')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="year_of_birth">{{ __('common.year_of_birth') }}</label>
                <input type="text" id="year_of_birth" name="year_of_birth" class="form-control" value="{{ old('year_of_birth', $client->year_of_birth) }}" placeholder="YYYY">
                @error('year_of_birth')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="region">{{ __('common.region') }}</label>
                <input type="text" id="region" name="region" class="form-control" value="{{ old('region', $client->region) }}">
                @error('region')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="home_town">{{ __('common.home_town') }}</label>
                <input type="text" id="home_town" name="home_town" class="form-control" value="{{ old('home_town', $client->home_town) }}">
                @error('home_town')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="nationality">{{ __('common.nationality') }}</label>
                <input type="text" id="nationality" name="nationality" class="form-control" value="{{ old('nationality', $client->nationality) }}">
                @error('nationality')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <!-- Next of Kin Information -->
        <h2 style="color: var(--accent-color); margin-bottom: 20px; margin-top: 40px;">{{ __('common.next_of_kin_information') }}</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="next_of_kin_first_name">{{ __('common.next_of_kin_first_name') }}</label>
                <input type="text" id="next_of_kin_first_name" name="next_of_kin_first_name" class="form-control" value="{{ old('next_of_kin_first_name', $client->next_of_kin_first_name) }}">
                @error('next_of_kin_first_name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="next_of_kin_middle_name">{{ __('common.next_of_kin_middle_name') }}</label>
                <input type="text" id="next_of_kin_middle_name" name="next_of_kin_middle_name" class="form-control" value="{{ old('next_of_kin_middle_name', $client->next_of_kin_middle_name) }}">
                @error('next_of_kin_middle_name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="next_of_kin_last_name">{{ __('common.next_of_kin_last_name') }}</label>
                <input type="text" id="next_of_kin_last_name" name="next_of_kin_last_name" class="form-control" value="{{ old('next_of_kin_last_name', $client->next_of_kin_last_name) }}">
                @error('next_of_kin_last_name')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="next_of_kin_phone_number">{{ __('common.next_of_kin_phone_number') }}</label>
                <input type="text" id="next_of_kin_phone_number" name="next_of_kin_phone_number" class="form-control" value="{{ old('next_of_kin_phone_number', $client->next_of_kin_phone_number) }}">
                @error('next_of_kin_phone_number')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label" for="next_of_kin_address">{{ __('common.next_of_kin_address') }}</label>
                <textarea id="next_of_kin_address" name="next_of_kin_address" class="form-control" rows="3">{{ old('next_of_kin_address', $client->next_of_kin_address) }}</textarea>
                @error('next_of_kin_address')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="next_of_kin_gender">{{ __('common.next_of_kin_gender') }}</label>
                <select id="next_of_kin_gender" name="next_of_kin_gender" class="form-select">
                    <option value="">{{ __('common.select_gender') }}</option>
                    <option value="Male" {{ old('next_of_kin_gender', $client->next_of_kin_gender) == 'Male' ? 'selected' : '' }}>Male</option>
                    <option value="Female" {{ old('next_of_kin_gender', $client->next_of_kin_gender) == 'Female' ? 'selected' : '' }}>Female</option>
                    <option value="Other" {{ old('next_of_kin_gender', $client->next_of_kin_gender) == 'Other' ? 'selected' : '' }}>Other</option>
                </select>
                @error('next_of_kin_gender')
                    <div style="color: #ff6b6b; margin-top: 5px; font-size: 14px;">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 30px;">
            <button type="submit" class="btn">{{ __('common.update') }}</button>
            <a href="{{ route('clients.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

