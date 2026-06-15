# Configurable Core Banking Integration

This document describes how to configure a non-KATO core banking system using the JSON mapper module.

## Overview

Business units can be linked to a **configurable** core banking system (`driver = configurable`). For each assigned transaction type, you define:

- HTTP method and endpoint path (relative to CBS `base_url`)
- Request field mappings (internal fields → CBS JSON)
- Response field mappings (CBS JSON → internal keys)
- Authentication credentials on the business unit

KATO integrations (`driver = kato`) continue to use the legacy `KatoService` unchanged.

## Setup Steps

1. **Create a CBS** (Super Admin → Core Banking Systems)
   - Set **Driver** to `Configurable (JSON mapper)`
   - Set **Base URL** (e.g. `https://cbs.example.com/api/v1`)
   - Choose **Authentication Type**: `JWT Bearer`, `Basic Auth`, or `OAuth Password Login`
   - Configure token/login paths when using OAuth or JWT refresh

2. **Assign transaction types** to the business unit (existing transaction-types assignment screen)

3. **Assign the CBS** to the business unit and enter credentials:
   - Username / password (Basic or OAuth login)
   - Bearer token (static JWT)
   - Extra headers JSON (e.g. `{"X-Company-ID": "guid"}`)

4. **Create CBS mappings** (Business Unit → Manage CBS Mappings)
   - Select a transaction type from the dropdown (only types assigned to that BU)
   - Configure endpoint and field mappings

## Configuration Walkthrough (Super Admin)

You create the driver in one place and map fields in another. Everything is built in — no code changes required.

### Two places in Super Admin

| Step | Where | What you configure |
|------|-------|--------------------|
| 1 | Core Banking Systems | CBS provider, driver, base URL, auth type |
| 2 | Business Unit → CBS Mappings | Field mappings per transaction type |

KATO uses driver `kato` (legacy, no field mapper). Any other CBS uses driver **Configurable (JSON mapper)**.

### Step 1 — Create the CBS driver

Super Admin → Core Banking Systems → Create (`/superadmin/core-banking-systems/create`). Set:

- **Name** — e.g. `Flexcube`
- **Code** — e.g. `flexcube`
- **Base URL** — API root, e.g. `https://cbs.example.com/api/v1`
- **Driver** — choose **Configurable (JSON mapper)** (not KATO)
- **Authentication Type** — JWT Bearer, Basic Auth, or OAuth Password Login
- **Timeout** — default 30 seconds

For OAuth / JWT refresh, fill the auth config (token endpoint, token JSON path, etc.) in the same form.

### Step 2 — Assign transaction types to the business unit

Mappings only work for transaction types already assigned to that BU.

Super Admin → Business Units → (edit BU) — check transaction types on the edit form, or use `/superadmin/business-units/{id}/transaction-types`.

Example: assign **Cash Deposit (210000)** and **Cash Withdrawal (0110000)**.

### Step 3 — Link CBS + credentials on the business unit

Super Admin → Business Units → Edit:

- Select your configurable CBS in **Select CBS**
- A **Core Banking Credentials** section appears:
  - Username / password (Basic or OAuth)
  - Bearer token (static JWT)
  - Extra headers JSON, e.g. `{"X-Company-ID": "your-guid"}`
- Save the business unit.

### Step 4 — Map fields (this is where mapping happens)

Business Unit Edit → **Manage CBS Mappings** (`/superadmin/business-units/{businessUnitId}/cbs-mappings`), or add a mapping at `/superadmin/business-units/{businessUnitId}/cbs-mappings/create`.

For each mapping:

- **Transaction type** — dropdown of types assigned to that BU (one mapping per type)
- **HTTP method** — POST / GET / etc.
- **Endpoint path** — relative to base URL, e.g. `transactions/deposit`
- **Request field mappings** — your internal fields → CBS JSON keys
- **Response field mappings** — CBS response paths → internal keys
- **Success path / value** — e.g. `success` = `true`
- **Error message path** — e.g. `message`

#### Request mapping example (deposit)

| Source field | Target JSON key | Transform |
|--------------|-----------------|-----------|
| `account.account_number` | `acctNo` | none |
| `transaction.amount` | `txnAmount` | float |
| `agent.id` | `tellerId` | integer |
| Literal (empty source) | `channel` | default: `CASH` |

### How it runs at transaction time

```
Terminal sends transaction_type_code → ApiTransactionController → CbsGateway → External CBS API
```

When a terminal posts e.g. `transaction_type_code: "210000"`:

1. The API resolves that transaction type for the BU
2. If a CBS mapping exists → it builds JSON from your mappings and POSTs to the CBS
3. If the CBS call fails → the transaction is rejected (same rule as KATO)
4. If no mapping exists → internal ledger only (no CBS call)

### Full walkthrough example

1. Create CBS: `Flexcube`, driver Configurable, base URL `https://cbs.example.com`, auth Basic
2. Assign Cash Deposit (210000) to the BU
3. On BU edit: select Flexcube, enter username / password
4. Manage CBS Mappings → Add:
   - Transaction type: Cash Deposit
   - `POST transactions/deposit`
   - Map `account.account_number` → `acctNo`, `transaction.amount` → `txnAmount`
   - Response: `transactionId` → `transactionId`
   - Success: `success` = `true`
5. Terminal deposit with `transaction_type_code: "210000"` → payload sent to CBS automatically

## Request Mapping Format

Each row maps an internal source to a CBS JSON key:

| Source | Target | Notes |
|--------|--------|-------|
| `transaction.amount` | `txnAmount` | Use transform `float` |
| `account.account_number` | `acctNo` | |
| `client.member_no` | `memberNo` | |
| `request.pin` | `memberPin` | From API request |
| Literal | `channel` | Leave source empty, set default `CASH` |

### Available source fields

- `transaction.amount`, `transaction.reference`, `transaction.type_code`
- `account.account_number`, `account.account_code`, `account.balance`
- `client.member_no`, `client.phone`, `client.first_name`, `client.last_name`, `client.id_passport`
- `agent.id`, `agent.username`
- `request.pin`, `request.otp`
- `business_unit.id`, `business_unit.name`

### Transforms

`none`, `uppercase`, `lowercase`, `float`, `integer`, `string`, `date:Y-m-d`

Nested targets use dot notation (e.g. `transaction.details.amount`).

## Response Mapping Format

| CBS Response Path | Internal Key |
|-------------------|--------------|
| `transactionId` | `transactionId` |
| `data.transactionId` | `transactionId` |

Mapped values are stored in transaction `metadata` as `cbs_transaction_id` and `cbs_api_response`.

## Authentication Types

### JWT Bearer (`jwt_bearer`)

Store a static bearer token on the business unit credentials. Optionally configure a token endpoint in CBS auth config for refresh when expired.

### Basic Auth (`basic`)

Store username and password on the business unit. Requests include `Authorization: Basic base64(user:pass)`.

### OAuth Password (`oauth_password`)

Store username/password on the business unit. The module POSTs to the configured token endpoint, extracts the token from the configured JSON path, and uses `Authorization: Bearer {token}`.

## Runtime Behavior

When a terminal posts a transaction with `transaction_type_code`:

1. The API resolves the transaction type (must be assigned to the BU)
2. If KATO is enabled → legacy KATO path
3. Else if a CBS mapping exists for `(business_unit_id, transaction_type_id)` → POST to CBS
4. If CBS call fails → transaction is rejected (CBS-first, same as KATO)
5. If no mapping → internal ledger only

## Example: Cash Deposit

**CBS:** `https://cbs.example.com`, Basic auth

**Mapping for transaction type `210000`:**

- POST `transactions/deposit`
- `account.account_number` → `acctNo`
- `transaction.amount` → `txnAmount` (float)
- Response: `transactionId` → `transactionId`
- Success path: `success` = `true`

**Terminal request:**

```json
{
  "business_unit_id": 1,
  "agent_id": 5,
  "pin": "123456",
  "account_number": "ACC-001",
  "amount": 5000,
  "transaction_type_code": "210000",
  "device_serial": "SN-001",
  "reference": "REF-UNIQUE-001"
}
```

**CBS receives:**

```json
{
  "acctNo": "ACC-001",
  "txnAmount": 5000
}
```
