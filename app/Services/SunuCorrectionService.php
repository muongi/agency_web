<?php

namespace App\Services;

use App\Models\Agent;
use App\Models\InsuranceProduct;
use App\Models\SunuCorrectionEvent;
use App\Models\SunuCorrectionRequest;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Models\SunuProposal;
use App\Models\Terminal;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Collector error reports: the collector asks from the TPE, a web user decides
 * (approve / reject, decision only), then a web user applies the correction by hand
 * or closes the request. Each step writes one SunuCorrectionEvent (who, when, before/after).
 *
 * Failures a user can fix are thrown as \DomainException with a translation key.
 */
class SunuCorrectionService
{
    /** How far back the TPE lists the collector's own operations. */
    public const OPERATIONS_DAYS = 7;

    public function __construct(
        protected CollectorCapService $capService,
        protected SunuSubscriptionService $subscriptions
    ) {
    }

    // ---------------------------------------------------------------- TPE side

    /**
     * Premiums and subscriptions of this agent, last OPERATIONS_DAYS days, newest first.
     */
    public function recentOperations(Agent $agent, int $buId, int $limit = 50): Collection
    {
        $since = now()->subDays(self::OPERATIONS_DAYS);

        $premiums = SunuPremiumCollection::with(['policy.client', 'policy.product', 'proposal.product'])
            ->where('business_unit_id', $buId)
            ->where('agent_id', $agent->id)
            ->where('created_at', '>=', $since)
            ->latest('id')->limit($limit)->get()
            ->map(fn (SunuPremiumCollection $p) => [
                'target_type' => SunuCorrectionRequest::TARGET_PREMIUM,
                'target_id' => $p->id,
                'reference' => $p->reference,
                'date' => optional($p->collected_at ?? $p->created_at)->toIso8601String(),
                'kind' => $p->collection_kind,
                'customer' => $this->premiumCustomerName($p),
                'number' => $p->policy?->policy_number ?? $p->proposal?->proposal_number,
                'product' => $p->policy?->product?->name_fr ?? $p->proposal?->product?->name_fr,
                'amount' => (float) $p->amount,
                'status' => $p->status,
            ]);

        $proposals = SunuProposal::with('product')
            ->where('business_unit_id', $buId)
            ->where('agent_id', $agent->id)
            ->where('created_at', '>=', $since)
            ->latest('id')->limit($limit)->get()
            ->map(fn (SunuProposal $p) => [
                'target_type' => SunuCorrectionRequest::TARGET_PROPOSAL,
                'target_id' => $p->id,
                'reference' => $p->proposal_number,
                'date' => optional($p->subscribed_at ?? $p->created_at)->toIso8601String(),
                'kind' => 'subscription',
                'customer' => trim(($p->last_name ?? '').' '.($p->first_names ?? '')),
                'number' => $p->proposal_number,
                'product' => $p->product?->name_fr,
                'amount' => null,
                'status' => $p->status,
                'last_name' => $p->last_name,
                'first_names' => $p->first_names,
                'phone' => $p->phone,
            ]);

        $all = $premiums->concat($proposals)->sortByDesc('date')->take($limit)->values();

        $open = SunuCorrectionRequest::where('business_unit_id', $buId)
            ->where('requested_by_agent_id', $agent->id)
            ->where('created_at', '>=', $since->copy()->subDays(1))
            ->latest('id')->get()
            ->groupBy(fn ($r) => $r->target_type.':'.$r->target_id);

        return $all->map(function (array $op) use ($open) {
            $last = optional($open->get($op['target_type'].':'.$op['target_id']))->first();
            $op['request_reference'] = $last?->reference;
            $op['request_status'] = $last?->status;

            return $op;
        });
    }

    /**
     * @param  array{target_type:string,target_id?:int|null,target_reference?:string|null,request_type:string,reason_code:string,details:string,proposed_changes?:array|null,client_reference?:string|null}  $data
     */
    public function createFromTpe(Agent $agent, ?Terminal $terminal, int $buId, array $data, ?string $deviceSerial): SunuCorrectionRequest
    {
        if (! empty($data['client_reference'])) {
            $existing = SunuCorrectionRequest::where('client_reference', $data['client_reference'])->first();
            if ($existing) {
                return $existing;
            }
        }

        $target = $this->findTarget($buId, $data['target_type'], $data['target_id'] ?? null, $data['target_reference'] ?? null);
        if (! $target) {
            throw new \DomainException('TARGET_NOT_FOUND');
        }
        if ((int) $target->agent_id !== (int) $agent->id) {
            throw new \DomainException('NOT_OWN_OPERATION');
        }
        if ($target instanceof SunuPremiumCollection && $target->isCancelled()) {
            throw new \DomainException('ALREADY_CANCELLED');
        }
        if ($target instanceof SunuProposal && $target->status === SunuProposal::STATUS_CANCELLED) {
            throw new \DomainException('ALREADY_CANCELLED');
        }

        $targetRef = $target instanceof SunuPremiumCollection ? $target->reference : $target->proposal_number;

        return DB::transaction(function () use ($agent, $terminal, $buId, $data, $deviceSerial, $target, $targetRef) {
            $open = SunuCorrectionRequest::forTarget($data['target_type'], $target->id)
                ->whereIn('status', SunuCorrectionRequest::OPEN_STATUSES)
                ->lockForUpdate()->first();
            if ($open) {
                throw new \DomainException('REQUEST_ALREADY_OPEN:'.$open->reference);
            }

            $request = SunuCorrectionRequest::create([
                'business_unit_id' => $buId,
                'reference' => $this->makeReference(),
                'target_type' => $data['target_type'],
                'target_id' => $target->id,
                'target_reference' => $targetRef,
                'request_type' => $data['request_type'],
                'reason_code' => $data['reason_code'],
                'details' => $data['details'],
                'proposed_changes' => $this->cleanProposed($data['proposed_changes'] ?? null),
                'status' => SunuCorrectionRequest::STATUS_PENDING,
                'requested_by_agent_id' => $agent->id,
                'terminal_id' => $terminal?->id,
                'device_serial' => $deviceSerial,
                'requested_at' => now(),
                'client_reference' => $data['client_reference'] ?? null,
            ]);

            $this->event($request, 'requested', SunuCorrectionEvent::ACTOR_AGENT, $agent->id, $agent->full_name,
                $request->details, $this->snapshot($target), $request->proposed_changes);

            Log::info('SUNU_CORRECTION_REQUESTED', [
                'reference' => $request->reference, 'agent_id' => $agent->id, 'target' => $request->target_type.':'.$target->id,
                'type' => $request->request_type, 'reason' => $request->reason_code, 'serial' => $deviceSerial,
            ]);

            return $request;
        });
    }

    // ---------------------------------------------------------------- decision

    public function approve(SunuCorrectionRequest $request, User $user, ?string $note): SunuCorrectionRequest
    {
        return $this->decide($request, $user, SunuCorrectionRequest::STATUS_APPROVED, $note);
    }

    public function reject(SunuCorrectionRequest $request, User $user, string $note): SunuCorrectionRequest
    {
        if (trim($note) === '') {
            throw new \DomainException('sunu_corr_error_note_required');
        }

        return $this->decide($request, $user, SunuCorrectionRequest::STATUS_REJECTED, $note);
    }

    protected function decide(SunuCorrectionRequest $request, User $user, string $status, ?string $note): SunuCorrectionRequest
    {
        return DB::transaction(function () use ($request, $user, $status, $note) {
            $request = SunuCorrectionRequest::lockForUpdate()->findOrFail($request->id);
            if ($request->status !== SunuCorrectionRequest::STATUS_PENDING) {
                throw new \DomainException('sunu_corr_error_not_pending');
            }
            $request->update([
                'status' => $status,
                'decided_by' => $user->id,
                'decided_at' => now(),
                'decision_note' => $note,
            ]);
            $this->event($request, $status, SunuCorrectionEvent::ACTOR_USER, $user->id, $user->name, $note);
            Log::info('SUNU_CORRECTION_DECIDED', ['reference' => $request->reference, 'status' => $status, 'by_user_id' => $user->id]);

            return $request;
        });
    }

    // ---------------------------------------------------------------- manual corrections

    public function cancelPremium(SunuCorrectionRequest $request, User $user, string $note): SunuCorrectionRequest
    {
        return $this->applyToPremium($request, $user, 'premium_cancelled', $note, function (SunuPremiumCollection $p) use ($request, $user) {
            $p->update([
                'status' => SunuPremiumCollection::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'correction_request_id' => $request->id,
            ]);
            if ($p->agent_id) {
                $this->capService->adjustCollection($p->agent_id, -1 * (float) $p->amount);
            }
        });
    }

    public function changePremiumAmount(SunuCorrectionRequest $request, User $user, float $amount, string $note): SunuCorrectionRequest
    {
        if ($amount <= 0) {
            throw new \DomainException('sunu_corr_error_amount');
        }

        return $this->applyToPremium($request, $user, 'premium_amount_changed', $note, function (SunuPremiumCollection $p) use ($request, $amount) {
            $delta = $amount - (float) $p->amount;
            $p->update(['amount' => $amount, 'correction_request_id' => $request->id]);
            if ($p->agent_id && abs($delta) > 0.001) {
                $this->capService->adjustCollection($p->agent_id, $delta);
            }
        });
    }

    /** A policy number may cover several products: the product picks the right policy. */
    public function changePremiumPolicy(SunuCorrectionRequest $request, User $user, string $policyNumber, ?int $productId, string $note): SunuCorrectionRequest
    {
        return $this->applyToPremium($request, $user, 'premium_policy_changed', $note, function (SunuPremiumCollection $p) use ($request, $policyNumber, $productId) {
            try {
                $policy = $this->subscriptions->findPolicy($p->business_unit_id, $policyNumber, $productId);
            } catch (\DomainException $e) {
                throw new \DomainException('sunu_corr_error_policy_ambiguous');
            }
            if (! $policy) {
                throw new \DomainException('sunu_corr_error_policy_not_found');
            }
            $currentClient = $p->policy?->client_id ?? $p->proposal?->client_id;
            if ($request->reason_code !== 'wrong_customer' && $currentClient && (int) $policy->client_id !== (int) $currentClient) {
                throw new \DomainException('sunu_corr_error_policy_other_customer');
            }
            $p->update(['policy_id' => $policy->id, 'proposal_id' => $policy->proposal_id, 'correction_request_id' => $request->id]);
        });
    }

    public function cancelProposal(SunuCorrectionRequest $request, User $user, string $note): SunuCorrectionRequest
    {
        return $this->applyToProposal($request, $user, 'proposal_cancelled', $note, function (SunuProposal $p) {
            $hasPolicy = SunuPolicy::where('proposal_id', $p->id)->exists();
            $hasPremiums = SunuPremiumCollection::where('proposal_id', $p->id)->valid()->exists();
            if ($hasPolicy || $hasPremiums) {
                throw new \DomainException('sunu_corr_error_proposal_has_payments');
            }
            $p->update(['status' => SunuProposal::STATUS_CANCELLED]);
        });
    }

    /**
     * @param  array{last_name?:string|null,first_names?:string|null,phone?:string|null,insurance_product_id?:int|null}  $fields
     */
    public function changeProposalIdentity(SunuCorrectionRequest $request, User $user, array $fields, string $note): SunuCorrectionRequest
    {
        return $this->applyToProposal($request, $user, 'proposal_identity_changed', $note, function (SunuProposal $p) use ($fields) {
            $update = [];
            foreach (['last_name', 'first_names', 'phone'] as $k) {
                if (isset($fields[$k]) && trim((string) $fields[$k]) !== '') {
                    $update[$k] = trim((string) $fields[$k]);
                }
            }
            if (! empty($fields['insurance_product_id'])) {
                $product = InsuranceProduct::where('business_unit_id', $p->business_unit_id)->find($fields['insurance_product_id']);
                if (! $product) {
                    throw new \DomainException('sunu_corr_error_product');
                }
                if (SunuPolicy::where('proposal_id', $p->id)->exists() && $product->id !== $p->insurance_product_id) {
                    throw new \DomainException('sunu_corr_error_product_has_policy');
                }
                $update['insurance_product_id'] = $product->id;
            }
            if ($update === []) {
                throw new \DomainException('sunu_corr_error_nothing_to_change');
            }
            $p->update($update);
            if ($p->client) {
                $p->client->update(array_filter([
                    'last_name' => $update['last_name'] ?? null,
                    'first_name' => $update['first_names'] ?? null,
                    'phone_number' => $update['phone'] ?? null,
                ], fn ($v) => $v !== null));
            }
        });
    }

    /** Approved request that needs no data change (e.g. handled outside the BO). */
    public function close(SunuCorrectionRequest $request, User $user, string $note): SunuCorrectionRequest
    {
        if (trim($note) === '') {
            throw new \DomainException('sunu_corr_error_note_required');
        }

        return DB::transaction(function () use ($request, $user, $note) {
            $request = $this->lockApproved($request);
            $this->markDone($request, $user, $note);
            $this->event($request, 'closed', SunuCorrectionEvent::ACTOR_USER, $user->id, $user->name, $note);

            return $request;
        });
    }

    // ---------------------------------------------------------------- helpers

    protected function applyToPremium(SunuCorrectionRequest $request, User $user, string $event, string $note, callable $change): SunuCorrectionRequest
    {
        return DB::transaction(function () use ($request, $user, $event, $note, $change) {
            $request = $this->lockApproved($request, SunuCorrectionRequest::TARGET_PREMIUM);
            $premium = SunuPremiumCollection::lockForUpdate()->findOrFail($request->target_id);
            if ($premium->isCancelled()) {
                throw new \DomainException('sunu_corr_error_already_cancelled');
            }
            $before = $this->snapshot($premium);
            $change($premium);
            $premium->refresh();

            return $this->finishApply($request, $user, $event, $note, $before, $this->snapshot($premium));
        });
    }

    protected function applyToProposal(SunuCorrectionRequest $request, User $user, string $event, string $note, callable $change): SunuCorrectionRequest
    {
        return DB::transaction(function () use ($request, $user, $event, $note, $change) {
            $request = $this->lockApproved($request, SunuCorrectionRequest::TARGET_PROPOSAL);
            $proposal = SunuProposal::lockForUpdate()->findOrFail($request->target_id);
            if ($proposal->status === SunuProposal::STATUS_CANCELLED) {
                throw new \DomainException('sunu_corr_error_already_cancelled');
            }
            $before = $this->snapshot($proposal);
            $change($proposal);
            $proposal->refresh();

            return $this->finishApply($request, $user, $event, $note, $before, $this->snapshot($proposal));
        });
    }

    protected function finishApply(SunuCorrectionRequest $request, User $user, string $event, string $note, array $before, array $after): SunuCorrectionRequest
    {
        $this->markDone($request, $user, $note);
        $this->event($request, $event, SunuCorrectionEvent::ACTOR_USER, $user->id, $user->name, $note, $before, $after);
        Log::info('SUNU_CORRECTION_APPLIED', ['reference' => $request->reference, 'event' => $event, 'by_user_id' => $user->id]);

        return $request;
    }

    protected function lockApproved(SunuCorrectionRequest $request, ?string $targetType = null): SunuCorrectionRequest
    {
        $request = SunuCorrectionRequest::lockForUpdate()->findOrFail($request->id);
        if ($request->status !== SunuCorrectionRequest::STATUS_APPROVED) {
            throw new \DomainException('sunu_corr_error_not_approved');
        }
        if ($targetType && $request->target_type !== $targetType) {
            throw new \DomainException('sunu_corr_error_wrong_action');
        }

        return $request;
    }

    protected function markDone(SunuCorrectionRequest $request, User $user, string $note): void
    {
        $request->update([
            'status' => SunuCorrectionRequest::STATUS_DONE,
            'resolved_by' => $user->id,
            'resolved_at' => now(),
            'resolution_note' => $note,
        ]);
    }

    public function event(SunuCorrectionRequest $request, string $event, string $actorType, ?int $actorId, ?string $actorName,
        ?string $note = null, ?array $before = null, ?array $after = null): SunuCorrectionEvent
    {
        return SunuCorrectionEvent::create([
            'correction_request_id' => $request->id,
            'event' => $event,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'actor_name' => $actorName,
            'note' => $note,
            'before' => $before,
            'after' => $after,
        ]);
    }

    public function findTarget(int $buId, string $type, $id, ?string $reference): SunuPremiumCollection|SunuProposal|null
    {
        if ($type === SunuCorrectionRequest::TARGET_PREMIUM) {
            $q = SunuPremiumCollection::where('business_unit_id', $buId);

            return $id ? $q->find($id) : ($reference ? $q->where('reference', $reference)->first() : null);
        }
        if ($type === SunuCorrectionRequest::TARGET_PROPOSAL) {
            $q = SunuProposal::where('business_unit_id', $buId);

            return $id ? $q->find($id) : ($reference ? $q->where('proposal_number', $reference)->first() : null);
        }

        return null;
    }

    /** Values kept in the history (before / after a change). */
    public function snapshot($target): array
    {
        if ($target instanceof SunuPremiumCollection) {
            $target->loadMissing('policy.product');

            return [
                'reference' => $target->reference,
                'amount' => (float) $target->amount,
                'policy_number' => $target->policy?->policy_number,
                'product' => $target->policy?->product?->code,
                'proposal_id' => $target->proposal_id,
                'collection_kind' => $target->collection_kind,
                'status' => $target->status,
                'collected_at' => optional($target->collected_at)->toDateTimeString(),
            ];
        }
        if ($target instanceof SunuProposal) {
            return [
                'proposal_number' => $target->proposal_number,
                'last_name' => $target->last_name,
                'first_names' => $target->first_names,
                'phone' => $target->phone,
                'insurance_product_id' => $target->insurance_product_id,
                'status' => $target->status,
            ];
        }

        return [];
    }

    protected function premiumCustomerName(SunuPremiumCollection $p): string
    {
        $client = $p->policy?->client;
        if ($client) {
            return trim(($client->last_name ?? '').' '.($client->first_name ?? ''));
        }

        return trim(($p->proposal?->last_name ?? '').' '.($p->proposal?->first_names ?? ''));
    }

    protected function cleanProposed(?array $proposed): ?array
    {
        if (! $proposed) {
            return null;
        }
        $allowed = ['amount', 'policy_number', 'last_name', 'first_names', 'phone', 'product'];
        $clean = [];
        foreach ($allowed as $k) {
            if (isset($proposed[$k]) && trim((string) $proposed[$k]) !== '') {
                $clean[$k] = Str::limit(trim((string) $proposed[$k]), 120, '');
            }
        }

        return $clean ?: null;
    }

    protected function makeReference(): string
    {
        do {
            $ref = 'COR-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(4));
        } while (SunuCorrectionRequest::where('reference', $ref)->exists());

        return $ref;
    }
}
