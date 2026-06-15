<?php

namespace App\Http\Requests\Api;

use App\Support\Utf8Sanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCnssEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Strict enrollment rules (extra identification, files, beneficiary) apply to multipart bodies
     * and to any request that includes enrollment document uploads.
     */
    protected function isMultipartEnrollment(): bool
    {
        if (str_contains(strtolower((string) $this->header('Content-Type')), 'multipart/form-data')) {
            return true;
        }

        return $this->hasFile('id_document')
            || $this->hasFile('id_document_back')
            || $this->hasFile('birth_certificate');
    }

    protected function prepareForValidation(): void
    {
        $id = $this->input('identification', []);
        if (is_array($id)) {
            if (empty($id['address']) && ! empty($id['postal_address'])) {
                $id['address'] = $id['postal_address'];
            } elseif (empty($id['address'])) {
                $parts = array_filter([
                    $id['address_sector'] ?? null,
                    $id['district'] ?? null,
                    $id['department'] ?? null,
                    $id['province'] ?? null,
                    $id['postal_address'] ?? null,
                ]);
                if ($parts !== []) {
                    $id['address'] = implode(', ', $parts);
                }
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
        $multipartOnly = fn () => $this->isMultipartEnrollment();

        return [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'agent_id' => 'nullable|integer|exists:agents,id',
            'terminal_id' => 'nullable|string|max:255',
            'device_serial' => 'nullable|string|max:255',

            'id_document' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'file',
                'mimes:jpeg,jpg',
                'max:4096',
            ],
            'id_document_back' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'file',
                'mimes:jpeg,jpg',
                'max:4096',
            ],
            'birth_certificate' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'file',
                'mimes:jpeg,jpg',
                'max:4096',
            ],

            'identification' => 'required|array',
            'identification.last_name' => 'required|string|max:255',
            'identification.first_names' => 'required|string|max:255',
            'identification.gender' => 'required|string|max:32',
            'identification.date_of_birth' => 'required|date',
            'identification.phone' => 'required|string|max:64',
            'identification.maiden_name' => ['nullable', 'string', 'max:255'],
            'identification.marital_status' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:64',
            ],
            'identification.birth_department' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:255',
            ],
            'identification.country_of_birth' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:255',
            ],
            'identification.birth_certificate_number' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:255',
            ],
            'identification.birth_certificate_issue' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:255',
            ],
            'identification.nationality' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:128',
            ],
            'identification.address_sector' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:255',
            ],
            'identification.district' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:255',
            ],
            'identification.department' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:255',
            ],
            'identification.province' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:255',
            ],
            'identification.postal_address' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:2000',
            ],
            'identification.email' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'email',
                'max:255',
            ],
            'identification.address' => ['nullable', 'string', 'max:2000'],

            'other_information' => 'required|array',
            'other_information.branch_of_activity' => 'required|string|max:128',
            'other_information.occupation' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:255',
            ],
            'other_information.insurance_start_date' => 'required|date',
            'other_information.payment_frequency' => 'required|string|max:32',
            'other_information.insurance_type_art' => 'required|in:4,5',
            'other_information.declared_contribution_amount' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'numeric',
                'min:0',
            ],
            'other_information.declared_income' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'numeric',
                'min:0',
            ],
            'other_information.bank_account_number' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:128',
            ],
            'other_information.existing_cnss_number' => 'nullable|string|max:128',

            'beneficiary' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'array',
            ],
            'beneficiary.father_name' => ['nullable', 'string', 'max:255'],
            'beneficiary.mother_name' => ['nullable', 'string', 'max:255'],
            'beneficiary.spouse_name' => ['nullable', 'string', 'max:255'],
            'beneficiary.spouse_full_name' => ['nullable', 'string', 'max:255'],
            'beneficiary.children' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:2000',
            ],
            'beneficiary.dependent_children' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:2000',
            ],
            'beneficiary.address' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:2000',
            ],
            'beneficiary.beneficiary_address' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:2000',
            ],
            'beneficiary.emergency_contact_name' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:191',
            ],
            'beneficiary.emergency_contact_phone' => [
                Rule::requiredIf($multipartOnly),
                'nullable',
                'string',
                'max:32',
            ],
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
