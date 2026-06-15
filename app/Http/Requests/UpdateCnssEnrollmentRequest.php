<?php

namespace App\Http\Requests;

use App\Support\Utf8Sanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCnssEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $id = $this->input('identification', []);
        if (is_array($id)) {
            if (empty($id['address']) && ! empty($id['postal_address'])) {
                $id['address'] = $id['postal_address'];
            }
            $this->merge(['identification' => $id]);
        }

        $ben = $this->input('beneficiary', []);
        if (is_array($ben)) {
            if (isset($ben['dependent_children']) && ! isset($ben['children'])) {
                $ben['children'] = $ben['dependent_children'];
            }
            if (isset($ben['children']) && ! isset($ben['dependent_children'])) {
                $ben['dependent_children'] = $ben['children'];
            }
            if (isset($ben['beneficiary_address']) && ! isset($ben['address'])) {
                $ben['address'] = $ben['beneficiary_address'];
            }
            if (isset($ben['address']) && ! isset($ben['beneficiary_address'])) {
                $ben['beneficiary_address'] = $ben['address'];
            }
            if (isset($ben['spouse_full_name']) && ! isset($ben['spouse_name'])) {
                $ben['spouse_name'] = $ben['spouse_full_name'];
            }
            if (isset($ben['spouse_name']) && ! isset($ben['spouse_full_name'])) {
                $ben['spouse_full_name'] = $ben['spouse_name'];
            }
            $this->merge(['beneficiary' => $ben]);
        }

        foreach (['identification', 'other_information', 'beneficiary'] as $key) {
            if ($this->has($key)) {
                $this->merge([$key => Utf8Sanitizer::sanitize($this->input($key))]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'status' => 'required|in:pending,approved,rejected',

            'id_document' => ['nullable', 'file', 'mimes:jpeg,jpg', 'max:4096'],
            'id_document_back' => ['nullable', 'file', 'mimes:jpeg,jpg', 'max:4096'],
            'birth_certificate' => ['nullable', 'file', 'mimes:jpeg,jpg', 'max:4096'],

            'identification' => 'required|array',
            'identification.last_name' => 'required|string|max:255',
            'identification.first_names' => 'required|string|max:255',
            'identification.gender' => 'required|string|max:32',
            'identification.date_of_birth' => 'required|date',
            'identification.phone' => 'required|string|max:64',
            'identification.maiden_name' => ['nullable', 'string', 'max:255'],
            'identification.marital_status' => ['nullable', 'string', 'max:64'],
            'identification.nationality' => ['nullable', 'string', 'max:128'],
            'identification.address' => ['nullable', 'string', 'max:2000'],
            'identification.postal_address' => ['nullable', 'string', 'max:2000'],
            'identification.email' => ['nullable', 'email', 'max:255'],

            'other_information' => 'required|array',
            'other_information.branch_of_activity' => 'required|string|max:128',
            'other_information.occupation' => ['nullable', 'string', 'max:255'],
            'other_information.insurance_start_date' => 'required|date',
            'other_information.payment_frequency' => 'required|string|max:32',
            'other_information.insurance_type_art' => 'required|in:4,5',
            'other_information.declared_contribution_amount' => ['nullable', 'numeric', 'min:0'],
            'other_information.declared_income' => ['nullable', 'numeric', 'min:0'],
            'other_information.existing_cnss_number' => 'nullable|string|max:128',

            'beneficiary' => 'nullable|array',
            'beneficiary.father_name' => ['nullable', 'string', 'max:255'],
            'beneficiary.mother_name' => ['nullable', 'string', 'max:255'],
            'beneficiary.spouse_name' => ['nullable', 'string', 'max:255'],
            'beneficiary.spouse_full_name' => ['nullable', 'string', 'max:255'],
            'beneficiary.children' => ['nullable', 'string', 'max:2000'],
            'beneficiary.dependent_children' => ['nullable', 'string', 'max:2000'],
            'beneficiary.address' => ['nullable', 'string', 'max:2000'],
            'beneficiary.beneficiary_address' => ['nullable', 'string', 'max:2000'],
            'beneficiary.emergency_contact_name' => ['nullable', 'string', 'max:191'],
            'beneficiary.emergency_contact_phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $art = $this->input('other_information.insurance_type_art');
            if ((string) $art === '5' && empty($this->input('other_information.existing_cnss_number'))) {
                $validator->errors()->add(
                    'other_information.existing_cnss_number',
                    'The existing CNSS number is required when insurance type is Art. 5.'
                );
            }
        });
    }
}
