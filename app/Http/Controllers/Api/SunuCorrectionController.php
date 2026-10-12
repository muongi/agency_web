<?php

namespace App\Http\Controllers\Api;

use App\Models\SunuCorrectionRequest;
use App\Services\CollectorCapService;
use App\Services\SunuCorrectionService;
use App\Services\SunuSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * TPE "Signaler une erreur": the collector lists his recent operations, asks to cancel /
 * modify one (collection PIN required) and follows his requests. Decisions are taken in the web BO.
 * Extends SunuController for resolveAgentTerminal() / checkCollectionPin().
 */
class SunuCorrectionController extends SunuController
{
    public function __construct(
        CollectorCapService $capService,
        SunuSubscriptionService $subscriptions,
        protected SunuCorrectionService $corrections
    ) {
        parent::__construct($capService, $subscriptions);
    }

    public function operations(Request $request): JsonResponse
    {
        $resolved = $this->identify($request);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'days' => SunuCorrectionService::OPERATIONS_DAYS,
                'operations' => $this->corrections->recentOperations($resolved['agent'], (int) $request->input('business_unit_id')),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $resolved = $this->identify($request);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $rows = SunuCorrectionRequest::where('business_unit_id', (int) $request->input('business_unit_id'))
            ->where('requested_by_agent_id', $resolved['agent']->id)
            ->latest('id')->limit(50)->get()
            ->map(fn (SunuCorrectionRequest $r) => $this->payload($r));

        return response()->json(['success' => true, 'data' => ['requests' => $rows]]);
    }

    public function store(Request $request): JsonResponse
    {
        $v = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'device_serial' => 'required|string|max:128',
            'agent_id' => 'required|integer',
            'target_type' => ['required', Rule::in([SunuCorrectionRequest::TARGET_PREMIUM, SunuCorrectionRequest::TARGET_PROPOSAL])],
            'target_id' => 'nullable|integer|required_without:target_reference',
            'target_reference' => 'nullable|string|max:255|required_without:target_id',
            'request_type' => ['required', Rule::in([SunuCorrectionRequest::TYPE_CANCEL, SunuCorrectionRequest::TYPE_MODIFY])],
            'reason_code' => ['required', Rule::in(SunuCorrectionRequest::REASONS)],
            'details' => 'required|string|min:5|max:1000',
            'proposed_changes' => 'nullable|array',
            'proposed_changes.amount' => 'nullable|numeric|min:1',
            'proposed_changes.policy_number' => 'nullable|string|max:64',
            'proposed_changes.last_name' => 'nullable|string|max:120',
            'proposed_changes.first_names' => 'nullable|string|max:200',
            'proposed_changes.phone' => 'nullable|string|max:32',
            'proposed_changes.product' => 'nullable|string|max:120',
            'client_reference' => 'nullable|string|max:128',
            'pin' => 'nullable|string|max:12',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        $buId = (int) $request->input('business_unit_id');
        $resolved = $this->resolveAgentTerminal($request, $buId, true);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }
        $agent = $resolved['agent'];

        // A request is always online and signed with the collection PIN (no outbox replay without PIN).
        if ($agent->hasCollectionPin() && trim((string) $request->input('pin', '')) === '') {
            return response()->json(['success' => false, 'code' => 'PIN_REQUIRED', 'message' => 'PIN required'], 401);
        }
        $denied = $this->checkCollectionPin($request, $agent, 'correction');
        if ($denied) {
            return $denied;
        }

        try {
            $corr = $this->corrections->createFromTpe($agent, $resolved['terminal'] ?? null, $buId, $v->validated(), $request->input('device_serial'));
        } catch (\DomainException $e) {
            [$code, $ref] = array_pad(explode(':', $e->getMessage(), 2), 2, null);
            $status = match ($code) {
                'TARGET_NOT_FOUND' => 404,
                'NOT_OWN_OPERATION' => 403,
                default => 409,
            };
            Log::warning('SUNU_CORRECTION_REFUSED', ['code' => $code, 'agent_id' => $agent->id, 'target' => $request->input('target_type').':'.($request->input('target_id') ?? $request->input('target_reference'))]);

            return response()->json(['success' => false, 'code' => $code, 'message' => $this->refusalMessage($code), 'request_reference' => $ref], $status);
        }

        return response()->json(['success' => true, 'data' => $this->payload($corr)], 201);
    }

    /** Both serial and agent are required: the request is signed by the agent assigned to this F20. */
    protected function identify(Request $request): array|JsonResponse
    {
        $v = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'device_serial' => 'required|string|max:128',
            'agent_id' => 'required|integer',
        ]);
        if ($v->fails()) {
            return $this->failValidation($v);
        }

        return $this->resolveAgentTerminal($request, (int) $request->input('business_unit_id'), true);
    }

    protected function payload(SunuCorrectionRequest $r): array
    {
        return [
            'id' => $r->id,
            'reference' => $r->reference,
            'target_type' => $r->target_type,
            'target_reference' => $r->target_reference,
            'request_type' => $r->request_type,
            'reason_code' => $r->reason_code,
            'details' => $r->details,
            'proposed_changes' => $r->proposed_changes,
            'status' => $r->status,
            'requested_at' => optional($r->requested_at)->toIso8601String(),
            'decided_at' => optional($r->decided_at)->toIso8601String(),
            'decision_note' => $r->decision_note,
            'resolved_at' => optional($r->resolved_at)->toIso8601String(),
            'resolution_note' => $r->resolution_note,
        ];
    }

    protected function refusalMessage(string $code): string
    {
        return match ($code) {
            'TARGET_NOT_FOUND' => 'Operation not found',
            'NOT_OWN_OPERATION' => 'You can only report your own operations',
            'ALREADY_CANCELLED' => 'This operation is already cancelled',
            'REQUEST_ALREADY_OPEN' => 'A request is already open for this operation',
            default => 'Request refused',
        };
    }
}
