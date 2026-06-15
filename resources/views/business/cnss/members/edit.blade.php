@extends('layouts.app')

@section('title', __('common.cnss_edit_member'))
@section('page-title', __('common.cnss_edit_member'))

@section('content')
<div class="card">
    <div style="margin-bottom: 20px;">
        <h1 style="color: var(--accent-color); margin-bottom: 8px;">{{ __('common.cnss_edit_member') }}</h1>
        <p style="color: var(--text-muted); margin: 0;">
            <a href="{{ route('business.cnss.members.show', $enrollment->id) }}">{{ __('common.cnss_member_detail') }}</a>
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; padding: 16px; background: var(--bg-tertiary); border-radius: 8px;">
        <div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.cnss_voluntary_insured_number') }}</div>
            <div><code>{{ $enrollment->voluntary_insured_number }}</code></div>
        </div>
        <div>
            <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.cnss_affiliation_number') }}</div>
            <div><code>{{ $enrollment->cnss_registration_affiliation_number }}</code></div>
        </div>
        @if($enrollment->worker_number)
            <div>
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase;">{{ __('common.cnss_worker_number') }}</div>
                <div><code>{{ $enrollment->worker_number }}</code></div>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('business.cnss.members.update', $enrollment->id) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <div class="form-group" style="margin-bottom: 24px;">
            <label class="form-label">{{ __('common.status') }}</label>
            <select name="status" class="form-select" required>
                <option value="pending" {{ old('status', $enrollment->status) === 'pending' ? 'selected' : '' }}>{{ __('common.pending') }}</option>
                <option value="approved" {{ old('status', $enrollment->status) === 'approved' ? 'selected' : '' }}>{{ __('common.approved') }}</option>
                <option value="rejected" {{ old('status', $enrollment->status) === 'rejected' ? 'selected' : '' }}>{{ __('common.rejected') }}</option>
            </select>
            @error('status')<div style="color: var(--error-text); font-size: 12px; margin-top: 4px;">{{ $message }}</div>@enderror
        </div>

        <h2 style="color: var(--accent-color); font-size: 1.1rem; margin-bottom: 16px;">{{ __('common.cnss_identification') }}</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 24px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.last_name') }} *</label>
                <input type="text" name="identification[last_name]" class="form-control" value="{{ old('identification.last_name', $identification['last_name'] ?? $enrollment->last_name) }}" required>
                @error('identification.last_name')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.first_names') }} *</label>
                <input type="text" name="identification[first_names]" class="form-control" value="{{ old('identification.first_names', $identification['first_names'] ?? $enrollment->first_names) }}" required>
                @error('identification.first_names')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_gender') }} *</label>
                <input type="text" name="identification[gender]" class="form-control" value="{{ old('identification.gender', $identification['gender'] ?? '') }}" required>
                @error('identification.gender')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_date_of_birth') }} *</label>
                <input type="date" name="identification[date_of_birth]" class="form-control" value="{{ old('identification.date_of_birth', $identification['date_of_birth'] ?? '') }}" required>
                @error('identification.date_of_birth')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.phone') }} *</label>
                <input type="text" name="identification[phone]" class="form-control" value="{{ old('identification.phone', $identification['phone'] ?? $enrollment->phone) }}" required>
                @error('identification.phone')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_maiden_name') }}</label>
                <input type="text" name="identification[maiden_name]" class="form-control" value="{{ old('identification.maiden_name', $identification['maiden_name'] ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_marital_status') }}</label>
                <input type="text" name="identification[marital_status]" class="form-control" value="{{ old('identification.marital_status', $identification['marital_status'] ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_nationality') }}</label>
                <input type="text" name="identification[nationality]" class="form-control" value="{{ old('identification.nationality', $identification['nationality'] ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_email') }}</label>
                <input type="email" name="identification[email]" class="form-control" value="{{ old('identification.email', $identification['email'] ?? '') }}">
                @error('identification.email')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label">{{ __('common.cnss_address') }}</label>
                <textarea name="identification[address]" class="form-control" rows="2">{{ old('identification.address', $identification['address'] ?? $identification['postal_address'] ?? '') }}</textarea>
            </div>
        </div>

        <h2 style="color: var(--accent-color); font-size: 1.1rem; margin-bottom: 16px;">{{ __('common.cnss_other_information') }}</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 24px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_branch_of_activity') }} *</label>
                <input type="text" name="other_information[branch_of_activity]" class="form-control" value="{{ old('other_information.branch_of_activity', $otherInformation['branch_of_activity'] ?? '') }}" required>
                @error('other_information.branch_of_activity')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_occupation') }}</label>
                <input type="text" name="other_information[occupation]" class="form-control" value="{{ old('other_information.occupation', $otherInformation['occupation'] ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_insurance_start_date') }} *</label>
                <input type="date" name="other_information[insurance_start_date]" class="form-control" value="{{ old('other_information.insurance_start_date', $otherInformation['insurance_start_date'] ?? '') }}" required>
                @error('other_information.insurance_start_date')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_payment_frequency') }} *</label>
                <input type="text" name="other_information[payment_frequency]" class="form-control" value="{{ old('other_information.payment_frequency', $otherInformation['payment_frequency'] ?? '') }}" required>
                @error('other_information.payment_frequency')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_insurance_type_art') }} *</label>
                <select name="other_information[insurance_type_art]" class="form-select" required>
                    <option value="4" {{ old('other_information.insurance_type_art', $otherInformation['insurance_type_art'] ?? $enrollment->insurance_type_art) == '4' ? 'selected' : '' }}>Art. 4</option>
                    <option value="5" {{ old('other_information.insurance_type_art', $otherInformation['insurance_type_art'] ?? $enrollment->insurance_type_art) == '5' ? 'selected' : '' }}>Art. 5</option>
                </select>
                @error('other_information.insurance_type_art')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_declared_contribution_amount') }}</label>
                <input type="number" step="0.01" min="0" name="other_information[declared_contribution_amount]" class="form-control" value="{{ old('other_information.declared_contribution_amount', $otherInformation['declared_contribution_amount'] ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_declared_income') }}</label>
                <input type="number" step="0.01" min="0" name="other_information[declared_income]" class="form-control" value="{{ old('other_information.declared_income', $otherInformation['declared_income'] ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_existing_cnss_number') }}</label>
                <input type="text" name="other_information[existing_cnss_number]" class="form-control" value="{{ old('other_information.existing_cnss_number', $otherInformation['existing_cnss_number'] ?? '') }}">
                @error('other_information.existing_cnss_number')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
        </div>

        <h2 style="color: var(--accent-color); font-size: 1.1rem; margin-bottom: 16px;">{{ __('common.cnss_beneficiary') }}</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 24px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_emergency_contact_name') }}</label>
                <input type="text" name="beneficiary[emergency_contact_name]" class="form-control" value="{{ old('beneficiary.emergency_contact_name', $beneficiary['emergency_contact_name'] ?? $enrollment->emergency_contact_name) }}">
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_emergency_contact_phone') }}</label>
                <input type="text" name="beneficiary[emergency_contact_phone]" class="form-control" value="{{ old('beneficiary.emergency_contact_phone', $beneficiary['emergency_contact_phone'] ?? $enrollment->emergency_contact_phone) }}">
            </div>
            <div class="form-group">
                <label class="form-label">Father name</label>
                <input type="text" name="beneficiary[father_name]" class="form-control" value="{{ old('beneficiary.father_name', $beneficiary['father_name'] ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label">Mother name</label>
                <input type="text" name="beneficiary[mother_name]" class="form-control" value="{{ old('beneficiary.mother_name', $beneficiary['mother_name'] ?? '') }}">
            </div>
            <div class="form-group">
                <label class="form-label">Spouse name</label>
                <input type="text" name="beneficiary[spouse_name]" class="form-control" value="{{ old('beneficiary.spouse_name', $beneficiary['spouse_name'] ?? $beneficiary['spouse_full_name'] ?? '') }}">
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label">Children / dependents</label>
                <textarea name="beneficiary[children]" class="form-control" rows="2">{{ old('beneficiary.children', $beneficiary['children'] ?? $beneficiary['dependent_children'] ?? '') }}</textarea>
            </div>
            <div class="form-group" style="grid-column: 1 / -1;">
                <label class="form-label">Beneficiary address</label>
                <textarea name="beneficiary[address]" class="form-control" rows="2">{{ old('beneficiary.address', $beneficiary['address'] ?? $beneficiary['beneficiary_address'] ?? '') }}</textarea>
            </div>
        </div>

        <h2 style="color: var(--accent-color); font-size: 1.1rem; margin-bottom: 16px;">{{ __('common.cnss_enrollment_documents') }}</h2>
        <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 16px;">{{ __('common.cnss_replace_documents_hint') }}</p>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 24px;">
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_id_document_front') }}</label>
                <input type="file" name="id_document" class="form-control" accept="image/jpeg,image/jpg">
                @error('id_document')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_id_document_back') }}</label>
                <input type="file" name="id_document_back" class="form-control" accept="image/jpeg,image/jpg">
                @error('id_document_back')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">{{ __('common.cnss_birth_certificate') }}</label>
                <input type="file" name="birth_certificate" class="form-control" accept="image/jpeg,image/jpg">
                @error('birth_certificate')<div style="color: var(--error-text); font-size: 12px;">{{ $message }}</div>@enderror
            </div>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="submit" class="btn">{{ __('common.save') }}</button>
            <a href="{{ route('business.cnss.members.show', $enrollment->id) }}" class="btn btn-secondary">{{ __('common.cancel') }}</a>
        </div>
    </form>
</div>
@endsection
