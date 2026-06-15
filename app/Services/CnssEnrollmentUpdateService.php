<?php

namespace App\Services;

use App\Models\Client;
use App\Models\CnssVoluntaryEnrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CnssEnrollmentUpdateService
{
    public function applyFromRequest(CnssVoluntaryEnrollment $enrollment, Request $request): void
    {
        $identification = $request->input('identification', []);
        $other = $request->input('other_information', []);
        $beneficiary = $request->input('beneficiary', []);

        $existingForm = is_array($enrollment->form_data) ? $enrollment->form_data : [];
        $existingOther = is_array($existingForm['other_information'] ?? null) ? $existingForm['other_information'] : [];

        $mergedOther = array_merge($existingOther, is_array($other) ? $other : []);
        if (isset($existingOther['bank_account_number'])) {
            $mergedOther['bank_account_number'] = $existingOther['bank_account_number'];
        }
        if (isset($existingOther['declared_bank_account_number'])) {
            $mergedOther['declared_bank_account_number'] = $existingOther['declared_bank_account_number'];
        }

        $formData = [
            'identification' => is_array($identification) ? $identification : [],
            'other_information' => $mergedOther,
            'beneficiary' => is_array($beneficiary) ? $beneficiary : [],
        ];

        $emergencyName = is_array($beneficiary) ? ($beneficiary['emergency_contact_name'] ?? null) : null;
        $emergencyPhone = is_array($beneficiary) ? ($beneficiary['emergency_contact_phone'] ?? null) : null;

        $enrollment->fill([
            'status' => $request->input('status'),
            'last_name' => $identification['last_name'] ?? null,
            'first_names' => $identification['first_names'] ?? null,
            'phone' => $identification['phone'] ?? null,
            'insurance_type_art' => isset($mergedOther['insurance_type_art']) ? (string) $mergedOther['insurance_type_art'] : null,
            'form_data' => $formData,
            'emergency_contact_name' => $emergencyName ? (string) $emergencyName : null,
            'emergency_contact_phone' => $emergencyPhone ? (string) $emergencyPhone : null,
        ]);

        $this->storeReplacementDocuments($enrollment, $request);

        $enrollment->save();

        if ($enrollment->client_id) {
            $this->syncLinkedClient($enrollment, $identification);
        }
    }

    protected function storeReplacementDocuments(CnssVoluntaryEnrollment $enrollment, Request $request): void
    {
        $disk = Storage::disk('local');
        $basePath = 'cnss/enrollments/'.$enrollment->id;

        if ($request->hasFile('id_document')) {
            if ($enrollment->id_document_path && $disk->exists($enrollment->id_document_path)) {
                $disk->delete($enrollment->id_document_path);
            }
            $enrollment->id_document_path = $request->file('id_document')->store($basePath, 'local');
        }

        if ($request->hasFile('id_document_back')) {
            if ($enrollment->id_document_back_path && $disk->exists($enrollment->id_document_back_path)) {
                $disk->delete($enrollment->id_document_back_path);
            }
            $enrollment->id_document_back_path = $request->file('id_document_back')->store($basePath, 'local');
        }

        if ($request->hasFile('birth_certificate')) {
            if ($enrollment->birth_certificate_path && $disk->exists($enrollment->birth_certificate_path)) {
                $disk->delete($enrollment->birth_certificate_path);
            }
            $enrollment->birth_certificate_path = $request->file('birth_certificate')->store($basePath, 'local');
        }
    }

    protected function syncLinkedClient(CnssVoluntaryEnrollment $enrollment, array $identification): void
    {
        $client = Client::query()
            ->where('business_unit_id', $enrollment->business_unit_id)
            ->find($enrollment->client_id);

        if (! $client) {
            return;
        }

        $homeAddress = isset($identification['address']) ? (string) $identification['address'] : null;

        $client->update([
            'first_name' => (string) ($identification['first_names'] ?? $client->first_name),
            'last_name' => (string) ($identification['last_name'] ?? $client->last_name),
            'phone_number' => isset($identification['phone']) ? (string) $identification['phone'] : $client->phone_number,
            'email' => isset($identification['email']) ? (string) $identification['email'] : $client->email,
            'home_address' => $homeAddress ?? $client->home_address,
        ]);
    }

    public function deleteEnrollmentDocuments(CnssVoluntaryEnrollment $enrollment): void
    {
        $disk = Storage::disk('local');

        foreach (['id_document_path', 'id_document_back_path', 'birth_certificate_path'] as $column) {
            $path = $enrollment->{$column};
            if ($path && $disk->exists($path)) {
                $disk->delete($path);
            }
        }
    }
}
