<?php

namespace App\Services\Cbs;

use App\Models\Account;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Client;

class CbsContextBuilder
{
    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function build(BusinessUnit $businessUnit, array $context): array
    {
        $flat = [];

        $transaction = $context['transaction'] ?? [];
        foreach (['amount', 'reference', 'type_code'] as $key) {
            if (array_key_exists($key, $transaction)) {
                $flat['transaction.'.$key] = $transaction[$key];
            }
        }

        $account = $context['account'] ?? null;
        if ($account instanceof Account) {
            $flat['account.account_number'] = $account->account_number;
            $flat['account.account_code'] = $account->kato_account_code ?? null;
            $flat['account.balance'] = $account->balance;
        }

        $client = $context['client'] ?? ($account instanceof Account ? $account->accountable : null);
        if ($client instanceof Client) {
            $flat['client.member_no'] = $client->kato_member_no ?? null;
            $flat['client.phone'] = $client->phone_number ?? null;
            $flat['client.first_name'] = $client->first_name ?? null;
            $flat['client.last_name'] = $client->last_name ?? null;
            $flat['client.id_passport'] = $client->id_passport ?? null;
        }

        $agent = $context['agent'] ?? null;
        if ($agent instanceof Agent) {
            $flat['agent.id'] = $agent->id;
            $flat['agent.username'] = $agent->username ?? null;
        }

        $request = $context['request'] ?? [];
        foreach (['pin', 'otp'] as $key) {
            if (array_key_exists($key, $request)) {
                $flat['request.'.$key] = $request[$key];
            }
        }

        $flat['business_unit.id'] = $businessUnit->id;
        $flat['business_unit.name'] = $businessUnit->name;

        return $flat;
    }

    /**
     * @return array<int, string>
     */
    public static function sourceFieldOptions(): array
    {
        return [
            'transaction.amount',
            'transaction.reference',
            'transaction.type_code',
            'account.account_number',
            'account.account_code',
            'account.balance',
            'client.member_no',
            'client.phone',
            'client.first_name',
            'client.last_name',
            'client.id_passport',
            'agent.id',
            'agent.username',
            'request.pin',
            'request.otp',
            'business_unit.id',
            'business_unit.name',
        ];
    }
}
