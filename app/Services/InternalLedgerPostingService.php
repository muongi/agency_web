<?php

namespace App\Services;

use App\Exceptions\InternalLedgerPostingException;
use App\Models\Account;
use App\Models\Agent;
use App\Models\BusinessUnit;
use App\Models\Client;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class InternalLedgerPostingService
{
    /**
     * Apply internal ledger movements for a newly created transaction.
     *
     * No-op when the business unit uses core banking, the transaction is not
     * completed, the transaction type has no internal ledger flow, or the row
     * was already posted.
     *
     * Fee model (config internal_ledger.fee_payer = customer):
     * - Withdrawal: debit client (principal + transaction_cost); credit agent float
     *   (principal); credit agent commission (commission); credit BU business_fee
     *   (business_share).
     * - Deposit: debit agent float (principal); credit client (principal); debit
     *   client (transaction_cost); credit commission and business_share.
     *
     * @throws InternalLedgerPostingException
     */
    public function postForNewTransaction(Transaction $transaction): void
    {
        if ($transaction->ledger_posted_at) {
            return;
        }

        if ($transaction->status !== 'completed') {
            return;
        }

        $transaction->loadMissing(['businessUnit', 'transactionType', 'agent']);

        $businessUnit = $transaction->businessUnit;
        if (! $businessUnit || $businessUnit->core_banking_system_id !== null) {
            return;
        }

        $flow = $transaction->transactionType->internal_ledger_flow ?? null;
        if (! in_array($flow, ['cash_withdrawal', 'cash_deposit'], true)) {
            return;
        }

        if (config('internal_ledger.fee_payer') !== 'customer') {
            throw new InternalLedgerPostingException(
                'Internal ledger only supports fee_payer=customer.'
            );
        }

        if (! $this->feeTotalsMatch($transaction)) {
            throw new InternalLedgerPostingException(
                'Commission plus business share must equal transaction cost for internal ledger posting.'
            );
        }

        $accountNumber = $transaction->account_number;
        if ($accountNumber === null || $accountNumber === '') {
            throw new InternalLedgerPostingException(
                'Account number is required for internal ledger posting.'
            );
        }

        if (! $transaction->agent_id) {
            throw new InternalLedgerPostingException(
                'Agent is required for internal ledger posting.'
            );
        }

        DB::transaction(function () use ($transaction, $flow, $businessUnit, $accountNumber) {
            $lockedTx = Transaction::query()
                ->lockForUpdate()
                ->find($transaction->id);

            if (! $lockedTx || $lockedTx->ledger_posted_at) {
                return;
            }

            BusinessUnit::query()->whereKey($businessUnit->id)->lockForUpdate()->first();

            $customerAccount = Account::query()
                ->where('business_unit_id', $businessUnit->id)
                ->where('account_number', $accountNumber)
                ->where('accountable_type', Client::class)
                ->first();

            if (! $customerAccount) {
                throw new InternalLedgerPostingException(
                    'Client account not found for internal ledger posting.'
                );
            }

            $agent = Agent::query()
                ->where('business_unit_id', $businessUnit->id)
                ->find($transaction->agent_id);

            if (! $agent) {
                throw new InternalLedgerPostingException('Agent not found for internal ledger posting.');
            }

            $agentFloat = Account::query()
                ->where('business_unit_id', $businessUnit->id)
                ->where('accountable_type', Agent::class)
                ->where('accountable_id', $agent->id)
                ->where('account_type', 'float')
                ->first();

            $agentCommission = Account::query()
                ->where('business_unit_id', $businessUnit->id)
                ->where('accountable_type', Agent::class)
                ->where('accountable_id', $agent->id)
                ->where('account_type', 'commission')
                ->first();

            if (! $agentFloat || ! $agentCommission) {
                throw new InternalLedgerPostingException(
                    'Agent float or commission account missing for internal ledger posting.'
                );
            }

            $buFeeAccount = Account::query()
                ->where('business_unit_id', $businessUnit->id)
                ->where('accountable_type', BusinessUnit::class)
                ->where('accountable_id', $businessUnit->id)
                ->where('account_type', 'business_fee')
                ->first();

            if (! $buFeeAccount) {
                $buFeeAccount = $businessUnit->businessFeeAccount();
            }

            $accountIds = collect([
                $customerAccount->id,
                $agentFloat->id,
                $agentCommission->id,
                $buFeeAccount->id,
            ])->sort()->values()->all();

            $lockedAccounts = [];
            foreach ($accountIds as $accountId) {
                $lockedAccounts[$accountId] = Account::query()
                    ->whereKey($accountId)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $customerAccount = $lockedAccounts[$customerAccount->id];
            $agentFloat = $lockedAccounts[$agentFloat->id];
            $agentCommission = $lockedAccounts[$agentCommission->id];
            $buFeeAccount = $lockedAccounts[$buFeeAccount->id];

            $amount = (string) $lockedTx->transaction_amount;
            $cost = (string) $lockedTx->transaction_cost;
            $commission = (string) $lockedTx->commission;
            $businessShare = (string) $lockedTx->business_share;

            if ($flow === 'cash_withdrawal') {
                $customerDebit = bcadd($amount, $cost, 2);
                $this->assertBalanceAtLeast($customerAccount, $customerDebit);
                $this->applyDelta($customerAccount, bcmul($customerDebit, '-1', 2));
                $this->applyDelta($agentFloat, $amount);
                $this->applyDelta($agentCommission, $commission);
                $this->applyDelta($buFeeAccount, $businessShare);
            } else {
                $this->assertBalanceAtLeast($agentFloat, $amount);
                $this->applyDelta($agentFloat, bcmul($amount, '-1', 2));
                $this->applyDelta($customerAccount, $amount);
                if (bccomp($cost, '0', 2) > 0) {
                    $this->assertBalanceAtLeast($customerAccount, $cost);
                    $this->applyDelta($customerAccount, bcmul($cost, '-1', 2));
                    $this->applyDelta($agentCommission, $commission);
                    $this->applyDelta($buFeeAccount, $businessShare);
                }
            }

            $lockedTx->ledger_posted_at = now();
            $lockedTx->save();
        });
    }

    private function feeTotalsMatch(Transaction $transaction): bool
    {
        $sum = bcadd((string) $transaction->commission, (string) $transaction->business_share, 2);

        return bccomp($sum, (string) $transaction->transaction_cost, 2) === 0;
    }

    private function assertBalanceAtLeast(Account $account, string $minimum): void
    {
        $account->refresh();
        if (bccomp((string) $account->balance, $minimum, 2) < 0) {
            throw new InternalLedgerPostingException('Insufficient balance for internal ledger posting.');
        }
    }

    private function applyDelta(Account $account, string $signedDelta): void
    {
        if (bccomp($signedDelta, '0', 2) === 0) {
            return;
        }

        $account->refresh();
        $newBalance = bcadd((string) $account->balance, $signedDelta, 2);
        if (bccomp($newBalance, '0', 2) < 0) {
            throw new InternalLedgerPostingException('Internal ledger posting would make a balance negative.');
        }

        $account->balance = $newBalance;
        $account->save();
    }
}
