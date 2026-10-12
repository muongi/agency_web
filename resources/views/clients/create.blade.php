@extends('layouts.app')

@section('title', __('common.add_client'))
@section('page-title', __('common.add_client'))

@section('content')
<div class="card">
    <h1 style="margin-bottom: 24px; color: var(--accent-color);">{{ __('common.add_client') }}</h1>
    
    <form method="POST" action="{{ route('clients.store') }}" enctype="multipart/form-data">
        @csrf

        <!-- Personal Information -->
        <h2 style="color: var(--accent-color); margin-bottom: 20px; margin-top: 30px;">{{ __('common.personal_information') }}</h2>

        @if(!empty($isKato))
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <div class="form-group">
                    <label class="form-label" for="agent_id">
                        {{ __('common.kato_registration_agent') }}
                        <span class="text-error">*</span>
                    </label>
                    <select id="agent_id" name="agent_id" class="form-select" required>
                        <option value="">— {{ __('common.agent') }} —</option>
                        @foreach(($katoAgents ?? collect()) as $katoAgent)
                            <option value="{{ $katoAgent->id }}" {{ (string) old('agent_id') === (string) $katoAgent->id ? 'selected' : '' }}>
                                {{ $katoAgent->full_name }} ({{ $katoAgent->katoFloatAccountNumber() }})
                            </option>
                        @endforeach
                    </select>
                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">{{ __('common.kato_registration_agent_help') }}</small>
                    @error('agent_id')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        @endif
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="salutation">{{ __('common.salutation') }}</label>
                <select id="salutation" name="salutation" class="form-select">
                    <option value="">{{ __('common.select_salutation') }}</option>
                    <option value="Mr" {{ old('salutation') == 'Mr' ? 'selected' : '' }}>Mr</option>
                    <option value="Mrs" {{ old('salutation') == 'Mrs' ? 'selected' : '' }}>Mrs</option>
                    <option value="Ms" {{ old('salutation') == 'Ms' ? 'selected' : '' }}>Ms</option>
                    <option value="Dr" {{ old('salutation') == 'Dr' ? 'selected' : '' }}>Dr</option>
                </select>
                @error('salutation')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="first_name">{{ __('common.first_name') }} <span class="text-error">*</span></label>
                <input type="text" id="first_name" name="first_name" class="form-control" value="{{ old('first_name') }}" required>
                @error('first_name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="middle_name">{{ __('common.middle_name') }}</label>
                <input type="text" id="middle_name" name="middle_name" class="form-control" value="{{ old('middle_name') }}">
                @error('middle_name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="last_name">{{ __('common.last_name') }} <span class="text-error">*</span></label>
                <input type="text" id="last_name" name="last_name" class="form-control" value="{{ old('last_name') }}" required>
                @error('last_name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="email">{{ __('common.email') }}</label>
                <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}">
                @error('email')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="id_passport">{{ __('common.id_passport') }}</label>
                <input type="text" id="id_passport" name="id_passport" class="form-control" value="{{ old('id_passport') }}">
                @error('id_passport')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <label class="form-label" for="home_address">{{ __('common.home_address') }}</label>
                <textarea id="home_address" name="home_address" class="form-control" rows="3">{{ old('home_address') }}</textarea>
                @error('home_address')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="phone_number">{{ __('common.phone_number') }}</label>
                <input type="text" id="phone_number" name="phone_number" class="form-control" value="{{ old('phone_number') }}">
                @error('phone_number')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="photo">{{ __('common.photo') }}</label>
                <input type="file" id="photo" name="photo" class="form-control" accept="image/*">
                @error('photo')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="gender">
                    {{ __('common.gender') }}
                    @if(!empty($isKato))
                        <span class="text-error">*</span>
                    @endif
                </label>
                <select id="gender" name="gender" class="form-select" @if(!empty($isKato)) required @endif>
                    <option value="">{{ __('common.select_gender') }}</option>
                    <option value="Male" {{ old('gender') == 'Male' ? 'selected' : '' }}>{{ __('common.gender_male') }}</option>
                    <option value="Female" {{ old('gender') == 'Female' ? 'selected' : '' }}>{{ __('common.gender_female') }}</option>
                    @if(empty($isKato))
                        <option value="Other" {{ old('gender') == 'Other' ? 'selected' : '' }}>{{ __('common.gender_other') }}</option>
                    @endif
                </select>
                @if(!empty($isKato))
                    <small style="color: var(--text-muted); display: block; margin-top: 5px;">{{ __('common.kato_gender_required_note') }}</small>
                @endif
                @error('gender')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="marital_status">{{ __('common.marital_status') }}</label>
                <select id="marital_status" name="marital_status" class="form-select">
                    <option value="">{{ __('common.select_marital_status') }}</option>
                    <option value="Single" {{ old('marital_status') == 'Single' ? 'selected' : '' }}>{{ __('common.marital_single') }}</option>
                    <option value="Married" {{ old('marital_status') == 'Married' ? 'selected' : '' }}>{{ __('common.marital_married') }}</option>
                    <option value="Divorced" {{ old('marital_status') == 'Divorced' ? 'selected' : '' }}>{{ __('common.marital_divorced') }}</option>
                    <option value="Widowed" {{ old('marital_status') == 'Widowed' ? 'selected' : '' }}>{{ __('common.marital_widowed') }}</option>
                </select>
                @error('marital_status')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="year_of_birth">{{ __('common.year_of_birth') }}</label>
                <input type="text" id="year_of_birth" name="year_of_birth" class="form-control" value="{{ old('year_of_birth') }}" placeholder="YYYY">
                @error('year_of_birth')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="region">{{ __('common.region') }}</label>
                <input type="text" id="region" name="region" class="form-control" value="{{ old('region') }}">
                @error('region')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="home_town">{{ __('common.home_town') }}</label>
                <input type="text" id="home_town" name="home_town" class="form-control" value="{{ old('home_town') }}">
                @error('home_town')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="nationality">{{ __('common.nationality') }}</label>
                <input type="text" id="nationality" name="nationality" class="form-control" value="{{ old('nationality') }}">
                @error('nationality')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <!-- Next of Kin Information -->
        <h2 style="color: var(--accent-color); margin-bottom: 20px; margin-top: 40px;">{{ __('common.next_of_kin_information') }}</h2>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div class="form-group">
                <label class="form-label" for="next_of_kin_first_name">{{ __('common.next_of_kin_first_name') }}</label>
                <input type="text" id="next_of_kin_first_name" name="next_of_kin_first_name" class="form-control" value="{{ old('next_of_kin_first_name') }}">
                @error('next_of_kin_first_name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="next_of_kin_middle_name">{{ __('common.next_of_kin_middle_name') }}</label>
                <input type="text" id="next_of_kin_middle_name" name="next_of_kin_middle_name" class="form-control" value="{{ old('next_of_kin_middle_name') }}">
                @error('next_of_kin_middle_name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="next_of_kin_last_name">{{ __('common.next_of_kin_last_name') }}</label>
                <input type="text" id="next_of_kin_last_name" name="next_of_kin_last_name" class="form-control" value="{{ old('next_of_kin_last_name') }}">
                @error('next_of_kin_last_name')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="next_of_kin_phone_number">{{ __('common.next_of_kin_phone_number') }}</label>
                <input type="text" id="next_of_kin_phone_number" name="next_of_kin_phone_number" class="form-control" value="{{ old('next_of_kin_phone_number') }}">
                @error('next_of_kin_phone_number')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group detail-full">
                <label class="form-label" for="next_of_kin_address">{{ __('common.next_of_kin_address') }}</label>
                <textarea id="next_of_kin_address" name="next_of_kin_address" class="form-control" rows="3">{{ old('next_of_kin_address') }}</textarea>
                @error('next_of_kin_address')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label" for="next_of_kin_gender">{{ __('common.next_of_kin_gender') }}</label>
                <select id="next_of_kin_gender" name="next_of_kin_gender" class="form-select">
                    <option value="">{{ __('common.select_gender') }}</option>
                    <option value="Male" {{ old('next_of_kin_gender') == 'Male' ? 'selected' : '' }}>{{ __('common.gender_male') }}</option>
                    <option value="Female" {{ old('next_of_kin_gender') == 'Female' ? 'selected' : '' }}>{{ __('common.gender_female') }}</option>
                    <option value="Other" {{ old('next_of_kin_gender') == 'Other' ? 'selected' : '' }}>{{ __('common.gender_other') }}</option>
                </select>
                @error('next_of_kin_gender')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>
        </div>

        <div class="btn-row mt-30">
            <button type="submit" class="btn">{{ __('common.create') }}</button>
            <a href="{{ route('clients.index') }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection

