# KATO Fee & Commission Payload Update

Implementation plan for aligning KATO integration with the Agency Banking API spec. See `/cust/Agencyintergration.md` and `/cust/Agency_Banking_Developer_Guide.md` for the upstream API reference.

## Summary

- Persist KATO FLOAT/COMM account numbers on agent registration (`syncAgentAccountsFromKato`)
- Send `agentAccountNumber` + `accountNumber` + fee fields on deposit, withdraw, balance enquiry, mini-statement
- Compute fees via existing `TransactionCalculationService` and map through `KatoFeePayloadBuilder`
- New POST endpoints: `/api/v1/transactions/balance-enquiry`, `/api/v1/transactions/mini-statement`
- Global transaction types: `310000` (balance enquiry), `320000` (mini statement)
- Backfill command: `php artisan kato:backfill-agent-accounts`
