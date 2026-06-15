<?php

namespace App\Http\Requests\Api;

use App\Models\CnssContribution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCnssContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ledger = $this->input('mode', CnssContribution::MODE_RECORD) === CnssContribution::MODE_LEDGER;

        return [
            'business_unit_id' => 'nullable|integer|exists:business_units,id',
            'voluntary_no' => 'nullable|string|max:128',
            'affiliation_no' => 'nullable|string|max:128',
            'amount' => 'required|numeric|min:0.01',
            'reference_period' => 'required|string|max:32',
            'payment_frequency' => 'required|string|max:32',
            'mode' => ['nullable', 'string', Rule::in([CnssContribution::MODE_RECORD, CnssContribution::MODE_LEDGER])],
            'metadata' => 'nullable|array',

            'agent_id' => ['nullable', 'integer', 'exists:agents,id'],
            'pin' => Rule::when($ledger, ['required', 'string', 'min:4', 'max:255']),
            'device_serial' => Rule::when($ledger, ['required', 'string', 'max:255']),
            'account_number' => Rule::when($ledger, ['nullable', 'string', 'max:255']),
            'transaction_reference' => Rule::when($ledger, ['nullable', 'string', 'max:255', 'unique:transactions,reference']),
            'transaction_date' => Rule::when($ledger, ['nullable', 'date']),
            'status' => Rule::when($ledger, ['nullable', 'in:pending,completed,failed,cancelled']),
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $hasVoluntary = ! empty($this->input('voluntary_no'));
            $hasAffiliation = ! empty($this->input('affiliation_no'));

            if (! $hasVoluntary && ! $hasAffiliation) {
                $validator->errors()->add('voluntary_no', 'Either voluntary_no or affiliation_no is required.');
            }
        });
    }
}
