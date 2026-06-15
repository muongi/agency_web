<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Services\KatoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KatoMemberController extends Controller
{
    public function balancesByAccount(Request $request, string $accountNumber): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $businessUnit = BusinessUnit::find((int) $request->input('business_unit_id'));
        if (! $businessUnit || ! $businessUnit->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business unit not found or inactive',
            ], 404);
        }

        if (! $businessUnit->isKatoEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'KATO core banking is not enabled for this business unit',
            ], 422);
        }

        $account = $this->resolveClientAccount($businessUnit->id, $accountNumber);
        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'Client account not found for this business unit',
            ], 422);
        }

        $client = $account->accountable;
        if (! $client instanceof Client || ! $client->kato_member_no) {
            return response()->json([
                'success' => false,
                'message' => 'Client is not registered with KATO yet',
            ], 422);
        }

        $katoService = new KatoService();
        $result = $katoService->getMemberBalances($businessUnit, (string) $client->kato_member_no);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to fetch member balances',
            ], 422);
        }

        if (! empty($result['data']['balances'])) {
            $katoService->syncBalancesToLocalAccounts($client, $result['data']['balances']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'accountNumber' => $account->account_number,
                'memberNo' => (string) $client->kato_member_no,
                'balances' => $result['data']['balances'] ?? [],
            ],
        ]);
    }

    public function miniStatementByAccount(Request $request, string $accountNumber): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $businessUnit = BusinessUnit::find((int) $request->input('business_unit_id'));
        if (! $businessUnit || ! $businessUnit->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business unit not found or inactive',
            ], 404);
        }

        if (! $businessUnit->isKatoEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'KATO core banking is not enabled for this business unit',
            ], 422);
        }

        $account = $this->resolveClientAccount($businessUnit->id, $accountNumber);
        if (! $account) {
            return response()->json([
                'success' => false,
                'message' => 'Client account not found for this business unit',
            ], 422);
        }

        $client = $account->accountable;
        if (! $client instanceof Client || ! $client->kato_member_no) {
            return response()->json([
                'success' => false,
                'message' => 'Client is not registered with KATO yet',
            ], 422);
        }

        $limit = (int) ($request->input('limit', 5));
        $katoService = new KatoService();
        $result = $katoService->getMemberMiniStatement($businessUnit, (string) $client->kato_member_no, $limit);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to fetch mini-statement',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'accountNumber' => $account->account_number,
                'memberNo' => (string) $client->kato_member_no,
                'transactions' => $result['data']['transactions'] ?? [],
            ],
        ]);
    }

    public function balances(Request $request, string $memberNo): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'account_number' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $businessUnit = BusinessUnit::find((int) $request->input('business_unit_id'));
        if (! $businessUnit || ! $businessUnit->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business unit not found or inactive',
            ], 404);
        }

        if (! $businessUnit->isKatoEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'KATO core banking is not enabled for this business unit',
            ], 422);
        }

        $resolvedMemberNo = $this->resolveMemberNo($businessUnit->id, $memberNo, $request->input('account_number'));
        if ($resolvedMemberNo === null) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found or account_number does not match member',
            ], 422);
        }

        $katoService = new KatoService();
        $result = $katoService->getMemberBalances($businessUnit, $resolvedMemberNo);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to fetch member balances',
            ], 422);
        }

        $client = Client::where('business_unit_id', $businessUnit->id)
            ->where('kato_member_no', $resolvedMemberNo)
            ->first();

        if ($client && ! empty($result['data']['balances'])) {
            $katoService->syncBalancesToLocalAccounts($client, $result['data']['balances']);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    public function miniStatement(Request $request, string $memberNo): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'business_unit_id' => 'required|integer|exists:business_units,id',
            'account_number' => 'nullable|string|max:255',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $businessUnit = BusinessUnit::find((int) $request->input('business_unit_id'));
        if (! $businessUnit || ! $businessUnit->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Business unit not found or inactive',
            ], 404);
        }

        if (! $businessUnit->isKatoEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'KATO core banking is not enabled for this business unit',
            ], 422);
        }

        $resolvedMemberNo = $this->resolveMemberNo($businessUnit->id, $memberNo, $request->input('account_number'));
        if ($resolvedMemberNo === null) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found or account_number does not match member',
            ], 422);
        }

        $limit = (int) ($request->input('limit', 5));

        $katoService = new KatoService();
        $result = $katoService->getMemberMiniStatement($businessUnit, $resolvedMemberNo, $limit);

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['error'] ?? 'Failed to fetch mini-statement',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    /**
     * Resolve member number from path and optional account_number lookup.
     */
    private function resolveMemberNo(int $businessUnitId, string $memberNoFromPath, ?string $accountNumber): ?string
    {
        if ($accountNumber) {
            $account = Account::query()
                ->where('business_unit_id', $businessUnitId)
                ->where('account_number', $accountNumber)
                ->where('accountable_type', Client::class)
                ->first();

            $client = $account?->accountable;
            if (! $client instanceof Client) {
                return null;
            }

            $clientMemberNo = $client->kato_member_no;
            if (! $clientMemberNo || (string) $clientMemberNo !== (string) $memberNoFromPath) {
                return null;
            }

            return (string) $clientMemberNo;
        }

        return $memberNoFromPath !== '' ? $memberNoFromPath : null;
    }

    private function resolveClientAccount(int $businessUnitId, string $accountNumber): ?Account
    {
        return Account::query()
            ->where('business_unit_id', $businessUnitId)
            ->where('account_number', $accountNumber)
            ->where('accountable_type', Client::class)
            ->first();
    }
}
