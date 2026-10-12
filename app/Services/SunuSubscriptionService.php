<?php

namespace App\Services;

use App\Models\Card;
use App\Models\CardSettings;
use App\Models\Client;
use App\Models\SunuPolicy;
use App\Models\SunuPremiumCollection;
use App\Models\SunuProposal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Resolves what a customer is subscribed to (policies + pending proposals)
 * from an NFC card or a policy/proposal number, and links cards to subscriptions.
 */
class SunuSubscriptionService
{
    /** Policy statuses a collector may still take a premium for. */
    public const PAYABLE_POLICY_STATUSES = [
        SunuPolicy::STATUS_ACTIVE,
        SunuPolicy::STATUS_SUSPENDED,
        SunuPolicy::STATUS_LAPSED,
    ];

    /** UID formats accepted from manufacturer lists / web forms. */
    public const UID_FORMATS = ['hex', 'hex_reversed', 'decimal'];

    /**
     * Normalise a card UID to what the F20 reads: uppercase hex, bytes in reading order,
     * 4 to 10 bytes (8-20 even chars; ISO 14443 UIDs are 4/7/10). Returns null when the value cannot be a UID.
     */
    public function normalizeUid(?string $raw, string $format = 'hex'): ?string
    {
        $value = strtoupper(preg_replace('/[\s:\-]+/', '', (string) $raw));
        if ($value === '') {
            return null;
        }

        if ($format === 'decimal') {
            if (! ctype_digit($value)) {
                return null;
            }
            $hex = strtoupper(ltrim($this->decimalToHex($value), '0'));
            $len = strlen($hex) <= 8 ? 8 : (strlen($hex) <= 14 ? 14 : 20);
            $value = str_pad($hex, $len, '0', STR_PAD_LEFT);
        }

        $len = strlen($value);
        if (! preg_match('/^[0-9A-F]+$/', $value) || $len < 8 || $len > 20 || $len % 2 !== 0) {
            return null;
        }

        if ($format === 'hex_reversed') {
            $value = implode('', array_reverse(str_split($value, 2)));
        }

        return $value;
    }

    /** Decimal string → hex string without relying on 64-bit ints (10-byte UIDs overflow). */
    protected function decimalToHex(string $decimal): string
    {
        $hex = '';
        while ($decimal !== '0' && $decimal !== '') {
            $remainder = 0;
            $quotient = '';
            foreach (str_split($decimal) as $digit) {
                $current = $remainder * 10 + (int) $digit;
                $q = intdiv($current, 16);
                $remainder = $current % 16;
                if ($quotient !== '' || $q > 0) {
                    $quotient .= $q;
                }
            }
            $hex = dechex($remainder).$hex;
            $decimal = $quotient === '' ? '0' : $quotient;
        }

        return $hex === '' ? '0' : $hex;
    }

    public function findCard(int $businessUnitId, string $uid): ?Card
    {
        $uid = trim($uid);

        return Card::with('account')
            ->where('business_unit_id', $businessUnitId)
            ->where(function ($q) use ($uid) {
                $q->where('card_serial', $uid)->orWhere('card_number', $uid);
            })
            ->first();
    }

    /**
     * @return array{policies: Collection, proposals: Collection}
     */
    public function forCard(int $businessUnitId, Card $card): array
    {
        $policyIds = SunuPolicy::where('business_unit_id', $businessUnitId)
            ->whereHas('cards', fn ($c) => $c->where('cards.id', $card->id))
            ->pluck('id');
        $proposalIds = SunuProposal::where('business_unit_id', $businessUnitId)
            ->where('card_id', $card->id)
            ->pluck('id');

        $clientIds = SunuPolicy::whereIn('id', $policyIds)->pluck('client_id')
            ->merge(SunuProposal::whereIn('id', $proposalIds)->pluck('client_id'));

        if ($card->account?->accountable_type === Client::class) {
            $clientIds->push($card->account->accountable_id);
        }

        return $this->collect($businessUnitId, $clientIds, $policyIds, $proposalIds);
    }

    /** Customer whose INSURED number (N° assuré) this is. */
    public function clientForInsuredNumber(int $businessUnitId, ?string $number): ?Client
    {
        $number = trim((string) $number);

        return $number === '' ? null
            : Client::where('business_unit_id', $businessUnitId)->where('insured_number', $number)->first();
    }

    /**
     * Number typed on the TPE: an INSURED number (every product of that person), else a policy
     * or proposal number (that subscription plus the rest of the same customer).
     *
     * @return array{policies: Collection, proposals: Collection}|null null when the number is unknown
     */
    public function forNumber(int $businessUnitId, string $number): ?array
    {
        $number = trim($number);
        if ($number === '') {
            return null;
        }

        if ($client = $this->clientForInsuredNumber($businessUnitId, $number)) {
            $found = $this->forClient($businessUnitId, $client->id);

            return $found['policies']->isEmpty() && $found['proposals']->isEmpty() ? null : $found;
        }

        $policies = SunuPolicy::where('business_unit_id', $businessUnitId)
            ->where('policy_number', $number)
            ->get(['id', 'client_id']);
        $proposals = SunuProposal::where('business_unit_id', $businessUnitId)
            ->where(fn ($q) => $q->where('proposal_number', $number)->orWhere('sunulife_policy_number', $number))
            ->get(['id', 'client_id']);

        if ($policies->isEmpty() && $proposals->isEmpty()) {
            return null;
        }

        return $this->collect(
            $businessUnitId,
            $policies->pluck('client_id')->merge($proposals->pluck('client_id')),
            $policies->pluck('id'),
            $proposals->pluck('id')
        );
    }

    /**
     * Customer behind this number: INSURED number, else a policy, a proposal made under it, or a
     * proposal with that number, so a new product is added to the same client row.
     */
    public function clientForPolicyNumber(int $businessUnitId, ?string $number): ?Client
    {
        $number = trim((string) $number);
        if ($number === '') {
            return null;
        }

        if ($client = $this->clientForInsuredNumber($businessUnitId, $number)) {
            return $client;
        }

        $clientId = SunuPolicy::where('business_unit_id', $businessUnitId)->where('policy_number', $number)
            ->whereNotNull('client_id')->orderBy('id')->value('client_id')
            ?? SunuProposal::where('business_unit_id', $businessUnitId)->where('sunulife_policy_number', $number)
                ->whereNotNull('client_id')->orderBy('id')->value('client_id')
            ?? SunuProposal::where('business_unit_id', $businessUnitId)->where('proposal_number', $number)
                ->whereNotNull('client_id')->orderBy('id')->value('client_id');

        return $clientId ? Client::find($clientId) : null;
    }

    /**
     * Existing customer behind a number typed on the TPE (INSURED, policy or proposal number).
     * The new product gets its own policy number at acceptance; only the customer is shared.
     *
     * @return array{client: Client, number: string}|null
     */
    public function resolveExistingNumber(int $businessUnitId, ?string $number): ?array
    {
        $number = trim((string) $number);
        $client = $this->clientForPolicyNumber($businessUnitId, $number);

        return $client ? ['client' => $client, 'number' => $number] : null;
    }

    /**
     * Policy identity is (number, product). Without a product the number must be unambiguous.
     *
     * @throws \DomainException AMBIGUOUS_POLICY_NUMBER
     */
    public function findPolicy(int $businessUnitId, string $number, ?int $productId = null): ?SunuPolicy
    {
        $query = SunuPolicy::where('business_unit_id', $businessUnitId)->where('policy_number', trim($number));
        if ($productId) {
            return $query->where('insurance_product_id', $productId)->first();
        }
        $matches = $query->limit(2)->get();
        if ($matches->count() > 1) {
            throw new \DomainException('AMBIGUOUS_POLICY_NUMBER');
        }

        return $matches->first();
    }

    /**
     * What a premium typed by number pays: a payable policy, or an open proposal (2nd product
     * not yet a policy) under that number. With several products the product must be given;
     * the BO never guesses.
     *
     * @return array{policy: ?SunuPolicy, proposal: ?SunuProposal, candidates: Collection}|null
     *
     * @throws \DomainException AMBIGUOUS_POLICY_NUMBER
     */
    public function resolveForPremium(int $businessUnitId, string $number, ?int $productId = null): ?array
    {
        $number = trim($number);
        // INSURED number: every product of that person.
        $clientId = $this->clientForInsuredNumber($businessUnitId, $number)?->id;
        $policies = SunuPolicy::with('product')->where('business_unit_id', $businessUnitId)
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId), fn ($q) => $q->where('policy_number', $number))
            ->whereIn('status', self::PAYABLE_POLICY_STATUSES)
            ->orderBy('id')->get();
        $proposals = SunuProposal::with(['product', 'policy'])->where('business_unit_id', $businessUnitId)
            ->when($clientId,
                fn ($q) => $q->where('client_id', $clientId)->doesntHave('policy'),
                fn ($q) => $q->where(fn ($w) => $w->where('sunulife_policy_number', $number)->orWhere('proposal_number', $number)))
            ->whereNotIn('status', [SunuProposal::STATUS_REJECTED, SunuProposal::STATUS_CANCELLED])
            ->orderBy('id')->get()
            // A proposal already turned into a policy is represented by that policy.
            ->reject(fn ($p) => $p->policy && $policies->contains('id', $p->policy->id));

        $candidates = $policies->map(fn ($p) => ['policy' => $p, 'proposal' => null, 'product' => $p->product])
            ->concat($proposals->map(fn ($p) => ['policy' => $p->policy, 'proposal' => $p, 'product' => $p->product]))
            ->values();

        if ($productId) {
            $candidates = $candidates->filter(fn ($c) => (int) $c['product']?->id === $productId)->values();
        }
        if ($candidates->isEmpty()) {
            return null;
        }
        if ($candidates->pluck('product.id')->unique()->count() > 1) {
            $e = new \DomainException('AMBIGUOUS_POLICY_NUMBER');
            $this->lastAmbiguous = $candidates;
            throw $e;
        }

        $pick = $candidates->first();

        return ['policy' => $pick['policy'], 'proposal' => $pick['proposal'], 'candidates' => $candidates];
    }

    /** Candidates of the last AMBIGUOUS_POLICY_NUMBER, for the 422 product list. */
    public ?Collection $lastAmbiguous = null;

    /** Latest proposal of this customer with both CNIB photos (reused for a new product). */
    public function idDocumentsOnFile(int $businessUnitId, ?int $clientId): ?SunuProposal
    {
        if (! $clientId) {
            return null;
        }

        return SunuProposal::where('business_unit_id', $businessUnitId)
            ->where('client_id', $clientId)
            ->whereNotNull('id_document_front_path')
            ->whereNotNull('id_document_back_path')
            ->latest('id')
            ->first();
    }

    /** Products (ids) the customer already holds: payable policies + open proposals. */
    public function productsHeldByClient(int $businessUnitId, int $clientId): Collection
    {
        $found = $this->forClient($businessUnitId, $clientId);

        return $found['policies']->pluck('insurance_product_id')
            ->merge($found['proposals']->pluck('insurance_product_id'))
            ->unique()->values();
    }

    /**
     * Policy of an accepted proposal. The policy number is typed by the back office (one per
     * product); without one (legacy callers) the number the proposal was made under, else
     * POL-{proposal_number}. Premiums already collected on the proposal move onto the policy.
     */
    public function ensurePolicyFromProposal(SunuProposal $proposal, ?string $policyNumber = null): SunuPolicy
    {
        if ($proposal->policy) {
            return $proposal->policy;
        }

        $product = $proposal->product;
        $policyNumber = trim((string) $policyNumber) ?: ($proposal->sunulife_policy_number ?: 'POL-'.$proposal->proposal_number);

        $policy = SunuPolicy::firstOrCreate(
            [
                'business_unit_id' => $proposal->business_unit_id,
                'policy_number' => $policyNumber,
                'insurance_product_id' => $proposal->insurance_product_id,
            ],
            [
                'client_id' => $proposal->client_id,
                'proposal_id' => $proposal->id,
                'status' => SunuPolicy::STATUS_ACTIVE,
                'effective_date' => now()->toDateString(),
                'premium_amount' => $product?->default_premium_amount,
                'premium_frequency' => $product?->default_premium_frequency ?? 'monthly',
                'source' => 'local_proposal',
            ]
        );

        $this->carryCardToPolicy($proposal, $policy);
        SunuPremiumCollection::where('proposal_id', $proposal->id)->whereNull('policy_id')
            ->update(['policy_id' => $policy->id]);
        $proposal->setRelation('policy', $policy);

        return $policy;
    }

    /** Policy number already used in this business unit (policy numbers are unique per product contract). */
    public function policyNumberTaken(int $businessUnitId, string $number): bool
    {
        return SunuPolicy::where('business_unit_id', $businessUnitId)->where('policy_number', trim($number))->exists();
    }

    /** Same person? Last + first names compared without case / accents / extra spaces. */
    public static function sameName(?string $lastA, ?string $firstA, ?string $lastB, ?string $firstB): bool
    {
        $key = fn ($l, $f) => Str::lower(Str::ascii(preg_replace('/\s+/', ' ', trim(($l ?? '').' '.($f ?? '')))));

        return $key($lastA, $firstA) === $key($lastB, $firstB);
    }

    public static function samePhone(?string $a, ?string $b): bool
    {
        $digits = fn ($p) => substr(preg_replace('/\D+/', '', (string) $p), -8);

        return $digits($a) === '' || $digits($b) === '' || $digits($a) === $digits($b);
    }

    /**
     * @return array{policies: Collection, proposals: Collection}
     */
    public function forClient(int $businessUnitId, int $clientId): array
    {
        return $this->collect($businessUnitId, collect([$clientId]), collect(), collect());
    }

    /**
     * Clients a card can be issued to: those with a payable policy or a pending proposal.
     * Each item: id, label ("LAST First — phone — POL-1, PRO-2 (Product)"), has_card.
     */
    public function registeredClients(int $businessUnitId): Collection
    {
        $policies = SunuPolicy::with('product:id,name_fr,name_en')
            ->withCount('cards')
            ->where('business_unit_id', $businessUnitId)
            ->whereIn('status', self::PAYABLE_POLICY_STATUSES)
            ->whereNotNull('client_id')
            ->get(['id', 'client_id', 'policy_number', 'insurance_product_id']);

        $proposals = SunuProposal::with('product:id,name_fr,name_en')
            ->where('business_unit_id', $businessUnitId)
            ->whereNotIn('status', [SunuProposal::STATUS_REJECTED, SunuProposal::STATUS_CANCELLED])
            ->doesntHave('policy')
            ->whereNotNull('client_id')
            ->get(['id', 'client_id', 'card_id', 'proposal_number', 'insurance_product_id']);

        $byClient = $policies->groupBy('client_id');
        $proposalsByClient = $proposals->groupBy('client_id');

        return Client::where('business_unit_id', $businessUnitId)
            ->whereIn('id', $byClient->keys()->merge($proposalsByClient->keys())->unique()->values())
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'phone_number'])
            ->map(function (Client $client) use ($byClient, $proposalsByClient) {
                $pols = $byClient->get($client->id, collect());
                $props = $proposalsByClient->get($client->id, collect());

                $numbers = $pols->map(fn ($p) => $p->policy_number.($p->product ? ' ('.$p->product->localizedName().')' : ''))
                    ->merge($props->map(fn ($p) => $p->proposal_number.($p->product ? ' ('.$p->product->localizedName().')' : '')));

                $label = trim($client->last_name.' '.$client->first_name);
                if ($client->phone_number) {
                    $label .= ' — '.$client->phone_number;
                }
                $label .= ' — '.$numbers->implode(', ');

                return [
                    'id' => $client->id,
                    'label' => $label,
                    'has_card' => $pols->contains(fn ($p) => $p->cards_count > 0) || $props->contains(fn ($p) => $p->card_id),
                ];
            })
            ->values();
    }

    /**
     * @return array{policies: Collection, proposals: Collection}
     */
    protected function collect(int $businessUnitId, Collection $clientIds, Collection $policyIds, Collection $proposalIds): array
    {
        $clientIds = $clientIds->filter()->unique()->values();

        $policies = SunuPolicy::with(['product', 'client'])
            ->withMax(['premiums' => fn ($q) => $q->valid()], 'collected_at')
            ->where('business_unit_id', $businessUnitId)
            ->whereIn('status', self::PAYABLE_POLICY_STATUSES)
            ->where(function ($q) use ($clientIds, $policyIds) {
                $q->whereIn('id', $policyIds->all())->orWhereIn('client_id', $clientIds->all());
            })
            ->orderBy('id')
            ->get();

        $proposals = SunuProposal::with(['product', 'client'])
            ->withMax(['premiums' => fn ($q) => $q->valid()], 'collected_at')
            ->where('business_unit_id', $businessUnitId)
            ->whereNotIn('status', [SunuProposal::STATUS_REJECTED, SunuProposal::STATUS_CANCELLED])
            ->doesntHave('policy')
            ->where(function ($q) use ($clientIds, $proposalIds) {
                $q->whereIn('id', $proposalIds->all())->orWhereIn('client_id', $clientIds->all());
            })
            ->orderBy('id')
            ->get();

        return ['policies' => $policies, 'proposals' => $proposals];
    }

    public function policyPayload(SunuPolicy $p): array
    {
        return [
            'type' => 'policy',
            'policy_id' => $p->id,
            'proposal_id' => $p->proposal_id,
            'number' => $p->policy_number,
            'status' => $p->status,
            'product_id' => $p->insurance_product_id,
            'product_code' => $p->product?->code,
            'product_name' => $p->product?->localizedName(),
            'product_name_fr' => $p->product?->name_fr,
            'product_name_en' => $p->product?->name_en,
            'premium_amount' => $p->premium_amount !== null
                ? (float) $p->premium_amount
                : ($p->product?->default_premium_amount !== null ? (float) $p->product->default_premium_amount : null),
            'premium_frequency' => $p->premium_frequency,
            'policy_cost_amount' => $p->product?->policy_cost_amount !== null ? (float) $p->product->policy_cost_amount : null,
            'next_due_date' => optional($p->next_due_date)?->toDateString(),
            'suggested_kind' => 'recurring_premium',
            'last_paid_at' => $this->isoOrNull($p->premiums_max_collected_at),
        ];
    }

    public function proposalPayload(SunuProposal $p): array
    {
        return [
            'type' => 'proposal',
            'policy_id' => null,
            'proposal_id' => $p->id,
            'number' => $p->proposal_number,
            'status' => $p->status,
            'product_id' => $p->insurance_product_id,
            'product_code' => $p->product?->code,
            'product_name' => $p->product?->localizedName(),
            'product_name_fr' => $p->product?->name_fr,
            'product_name_en' => $p->product?->name_en,
            'premium_amount' => $p->product?->default_premium_amount !== null ? (float) $p->product->default_premium_amount : null,
            'premium_frequency' => $p->product?->default_premium_frequency ?? 'monthly',
            'policy_cost_amount' => $p->product?->policy_cost_amount !== null ? (float) $p->product->policy_cost_amount : null,
            'next_due_date' => null,
            // First premium already paid while the office has not accepted yet → next one is recurring.
            'suggested_kind' => $p->premiums_max_collected_at ? 'recurring_premium' : 'first_premium',
            'last_paid_at' => $this->isoOrNull($p->premiums_max_collected_at),
        ];
    }

    /**
     * @param array{policies: Collection, proposals: Collection} $found
     */
    public function customerPayload(array $found): ?array
    {
        $first = $found['policies']->first() ?? $found['proposals']->first();
        if (! $first) {
            return null;
        }

        $client = $first->client;
        $name = $client
            ? trim(($client->last_name ?? '').' '.($client->first_name ?? ''))
            : trim(($first->last_name ?? '').' '.($first->first_names ?? ''));

        return [
            'client_id' => $first->client_id,
            'insured_number' => $client?->insured_number,
            'name' => $name,
            'last_name' => $client?->last_name ?? $first->last_name ?? null,
            'first_name' => $client?->first_name ?? $first->first_names ?? null,
            'phone' => $client?->phone_number ?? $first->phone ?? null,
            // TPE skips the CNIB photos for a new product when the file already has them.
            'has_id_documents' => (bool) $this->idDocumentsOnFile($first->business_unit_id, $first->client_id),
        ];
    }

    /**
     * Link an NFC card (by UID or card number) to a proposal or policy.
     * Creates an unassigned card record if the UID is unknown in this business unit.
     *
     * @throws InvalidArgumentException when the card already belongs to another customer
     */
    public function linkCard(int $businessUnitId, string $uid, SunuProposal|SunuPolicy $subscription): Card
    {
        $raw = strtoupper(trim($uid));
        $uid = $this->normalizeUid($raw) ?? $raw;
        $card = $this->findCard($businessUnitId, $uid);
        if (! $card) {
            // Only a well-formed UID may create a card; anything else must be an existing card number
            if ($this->normalizeUid($raw) === null) {
                throw new InvalidArgumentException("Card {$raw} not found (UID must be 8 to 20 hex characters).");
            }
            $card = $this->createUnassignedCard($businessUnitId, $uid);
        }

        $this->assertSameCustomer($businessUnitId, $card, $subscription);

        if ($subscription instanceof SunuPolicy) {
            $subscription->cards()->syncWithoutDetaching([$card->id]);
        } else {
            $subscription->card_id = $card->id;
            $subscription->save();
            if ($subscription->policy) {
                $subscription->policy->cards()->syncWithoutDetaching([$card->id]);
            }
        }

        return $card;
    }

    /**
     * Link one card to every subscription in a lookup result (all-or-nothing).
     *
     * @param array{policies: Collection, proposals: Collection} $found
     * @throws InvalidArgumentException when the card belongs to another customer
     */
    public function linkCardToAll(int $businessUnitId, string $uid, array $found): Card
    {
        return DB::transaction(function () use ($businessUnitId, $uid, $found) {
            $card = null;
            foreach ($found['policies']->concat($found['proposals']) as $subscription) {
                $card = $this->linkCard($businessUnitId, $uid, $subscription);
            }
            if (! $card) {
                throw new InvalidArgumentException('No subscription to link.');
            }

            return $card;
        });
    }

    /**
     * Would linkCardToAll() refuse this card for these subscriptions? Read-only twin of
     * assertSameCustomer(), used by the TPE write pre-check before the chip is touched.
     *
     * @param array{policies: Collection, proposals: Collection} $found
     */
    public function cardBelongsToOtherCustomer(int $businessUnitId, Card $card, array $found): bool
    {
        try {
            foreach ($found['policies']->concat($found['proposals']) as $subscription) {
                $this->assertSameCustomer($businessUnitId, $card, $subscription);
            }
        } catch (InvalidArgumentException) {
            return true;
        }

        return false;
    }

    /** Carry a proposal's card over to the policy created from it. */
    public function carryCardToPolicy(SunuProposal $proposal, SunuPolicy $policy): void
    {
        if ($proposal->card_id) {
            $policy->cards()->syncWithoutDetaching([$proposal->card_id]);
        }
    }

    protected function createUnassignedCard(int $businessUnitId, string $uid): Card
    {
        if (Card::withTrashed()->where('card_serial', $uid)->exists()) {
            throw new InvalidArgumentException("Card UID {$uid} is registered in another business unit.");
        }

        $settings = CardSettings::getOrCreateForBusinessUnit($businessUnitId);

        return Card::create([
            'business_unit_id' => $businessUnitId,
            'card_serial' => $uid,
            'card_number' => CardNumberGeneratorService::generate($businessUnitId),
            'status' => 'unassigned',
            'prefix' => $settings->card_prefix,
            'expiration_date' => $settings->getDefaultExpirationDate(),
            'daily_transaction_limit' => $settings->default_daily_transaction_limit,
            'daily_withdrawal_limit' => $settings->default_daily_withdrawal_limit,
        ]);
    }

    /**
     * SUNU creates a new Client row per proposal, so "same customer" is decided on
     * normalised last + first name rather than client_id.
     */
    protected function assertSameCustomer(int $businessUnitId, Card $card, SunuProposal|SunuPolicy $subscription): void
    {
        $target = $this->nameKey($subscription->client, $subscription);
        $found = $this->forCard($businessUnitId, $card);

        foreach ($found['policies']->concat($found['proposals']) as $existing) {
            if ($this->nameKey($existing->client, $existing) !== $target) {
                throw new InvalidArgumentException('Card already linked to another customer.');
            }
        }
    }

    protected function nameKey(?Client $client, SunuProposal|SunuPolicy $row): string
    {
        $last = $client?->last_name ?? ($row->last_name ?? '');
        $first = $client?->first_name ?? ($row->first_names ?? '');

        return Str::lower(Str::ascii(preg_replace('/\s+/', ' ', trim($last.' '.$first))));
    }

    protected function isoOrNull($value): ?string
    {
        return $value ? \Illuminate\Support\Carbon::parse($value)->toIso8601String() : null;
    }
}
