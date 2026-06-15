<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateCnssEnrollmentRequest;
use App\Models\CnssVoluntaryEnrollment;
use App\Services\CnssEnrollmentUpdateService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CnssMemberController extends Controller
{
    public function __construct(
        protected CnssEnrollmentUpdateService $enrollmentUpdateService,
        protected ExportService $exportService
    ) {
        $this->middleware(['auth', 'business.access']);
    }

    public function index(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! auth()->user()->hasPermission('view-cnss-members', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $enrollments = $this->membersQuery($request, $businessUnit->id)
            ->paginate(20)
            ->withQueryString();

        return view('business.cnss.members.index', compact('enrollments', 'businessUnit'));
    }

    public function export(Request $request)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! auth()->user()->hasPermission('view-cnss-members', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $format = $request->get('format', 'csv');
        $enrollments = $this->membersQuery($request, $businessUnit->id)
            ->with('agent')
            ->get();

        $data = $enrollments->map(function (CnssVoluntaryEnrollment $row) {
            return [
                'Voluntary Insured No.' => $row->voluntary_insured_number,
                'Affiliation No.' => $row->cnss_registration_affiliation_number,
                'Worker No.' => $row->worker_number ?? '-',
                'Last Name' => $row->last_name ?? '-',
                'First Names' => $row->first_names ?? '-',
                'Phone' => $row->phone ?? '-',
                'Status' => $row->status,
                'Insurance Art.' => $row->insurance_type_art ?? '-',
                'Effective Date' => $row->effective_date?->format('Y-m-d') ?? '-',
                'Registration Date' => $row->registration_date?->format('Y-m-d') ?? '-',
                'Agent' => $row->agent?->full_name ?? '-',
                'Created At' => $row->created_at?->format('Y-m-d H:i:s') ?? '-',
            ];
        });

        $headers = [
            'Voluntary Insured No.',
            'Affiliation No.',
            'Worker No.',
            'Last Name',
            'First Names',
            'Phone',
            'Status',
            'Insurance Art.',
            'Effective Date',
            'Registration Date',
            'Agent',
            'Created At',
        ];

        $filters = $request->only(['search', 'status']);
        $filename = $this->exportService->generateFilename('cnss_members', $filters);

        switch ($format) {
            case 'excel':
                return $this->exportService->exportExcel($data, $headers, $filename, 'CNSS Members');
            case 'pdf':
                return $this->exportService->exportPdf($data, $headers, $filename, 'CNSS Members Export', $filters);
            case 'csv':
            default:
                return $this->exportService->exportCsv($data, $headers, $filename);
        }
    }

    public function show(int $enrollment)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! auth()->user()->hasPermission('view-cnss-member', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $enrollmentModel = CnssVoluntaryEnrollment::query()
            ->where('business_unit_id', $businessUnit->id)
            ->with([
                'agent',
                'terminal',
                'contributions' => fn ($q) => $q->orderByDesc('id'),
            ])
            ->findOrFail($enrollment);

        $canViewTransactions = auth()->user()->isSuperAdmin()
            || auth()->user()->hasPermission('view-transaction', $businessUnit->id);

        $canEdit = auth()->user()->isSuperAdmin()
            || auth()->user()->hasPermission('edit-cnss-member', $businessUnit->id);
        $canDelete = auth()->user()->isSuperAdmin()
            || auth()->user()->hasPermission('delete-cnss-member', $businessUnit->id);

        return view('business.cnss.members.show', [
            'enrollment' => $enrollmentModel,
            'businessUnit' => $businessUnit,
            'canViewTransactions' => $canViewTransactions,
            'canEdit' => $canEdit,
            'canDelete' => $canDelete,
        ]);
    }

    public function edit(int $enrollment)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! auth()->user()->isSuperAdmin() && ! auth()->user()->hasPermission('edit-cnss-member', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $enrollmentModel = CnssVoluntaryEnrollment::query()
            ->where('business_unit_id', $businessUnit->id)
            ->findOrFail($enrollment);

        $formData = is_array($enrollmentModel->form_data) ? $enrollmentModel->form_data : [];
        $identification = $formData['identification'] ?? [];
        $otherInformation = $formData['other_information'] ?? [];
        $beneficiary = $formData['beneficiary'] ?? [];

        return view('business.cnss.members.edit', [
            'enrollment' => $enrollmentModel,
            'businessUnit' => $businessUnit,
            'identification' => $identification,
            'otherInformation' => $otherInformation,
            'beneficiary' => $beneficiary,
        ]);
    }

    public function update(UpdateCnssEnrollmentRequest $request, int $enrollment)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! auth()->user()->isSuperAdmin() && ! auth()->user()->hasPermission('edit-cnss-member', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $enrollmentModel = CnssVoluntaryEnrollment::query()
            ->where('business_unit_id', $businessUnit->id)
            ->findOrFail($enrollment);

        $this->enrollmentUpdateService->applyFromRequest($enrollmentModel, $request);

        return redirect()->route('business.cnss.members.show', $enrollmentModel->id)
            ->with('success', __('common.updated_successfully'));
    }

    public function destroy(int $enrollment)
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            return redirect()->route('business.dashboard')
                ->with('error', __('common.no_business_unit_assigned'));
        }

        if (! auth()->user()->isSuperAdmin() && ! auth()->user()->hasPermission('delete-cnss-member', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $enrollmentModel = CnssVoluntaryEnrollment::query()
            ->where('business_unit_id', $businessUnit->id)
            ->findOrFail($enrollment);

        if ($enrollmentModel->contributions()->exists()) {
            return redirect()->back()
                ->with('error', __('common.cnss_cannot_delete_member_with_contributions'));
        }

        $this->enrollmentUpdateService->deleteEnrollmentDocuments($enrollmentModel);
        $enrollmentModel->delete();

        return redirect()->route('business.cnss.members.index')
            ->with('success', __('common.deleted_successfully'));
    }

    public function document(int $enrollment, string $type): StreamedResponse
    {
        $businessUnit = auth()->user()->currentBusinessUnit;

        if (! $businessUnit) {
            abort(403, __('common.no_business_unit_assigned'));
        }

        if (! auth()->user()->isSuperAdmin() && ! auth()->user()->hasPermission('view-cnss-member', $businessUnit->id)) {
            abort(403, __('common.unauthorized_access'));
        }

        $column = match ($type) {
            'id_document' => 'id_document_path',
            'id_document_back' => 'id_document_back_path',
            'birth_certificate' => 'birth_certificate_path',
            default => null,
        };

        if ($column === null) {
            abort(404);
        }

        $enrollmentModel = CnssVoluntaryEnrollment::query()
            ->where('business_unit_id', $businessUnit->id)
            ->findOrFail($enrollment);

        $path = $enrollmentModel->{$column};

        if (! $path || ! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return Storage::disk('local')->response($path, null, [
            'Content-Type' => Storage::disk('local')->mimeType($path) ?: 'image/jpeg',
            'Content-Disposition' => 'inline',
        ]);
    }

    protected function membersQuery(Request $request, int $businessUnitId)
    {
        $query = CnssVoluntaryEnrollment::query()
            ->where('business_unit_id', $businessUnitId)
            ->orderByDesc('id');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('last_name', 'like', "%{$search}%")
                    ->orWhere('first_names', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('voluntary_insured_number', 'like', "%{$search}%")
                    ->orWhere('cnss_registration_affiliation_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return $query;
    }
}
