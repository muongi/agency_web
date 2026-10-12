# SUNU Back-office API (pilot)

Base: `/api/v1/sunu`

All POS calls should include `business_unit_id`. Prefer `device_serial` (terminal serial) so agent is resolved from assignment.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/products?business_unit_id=` | Active insurance products |
| POST | `/proposals` | Multipart: proposal + CNIB images, GPS, client identity |
| GET | `/proposals/{id}?business_unit_id=` | Proposal detail |
| POST | `/premiums` | Collect policy_cost / first_premium / recurring_premium (idempotent `reference`) |
| GET | `/policies/by-card/{uid}?business_unit_id=` | Active policies for NFC card |
| GET | `/collector/status?business_unit_id=&device_serial=` | Cap / uncollected / blocked |
| POST | `/pay-ins` | Declare collector pay-in |

Also reused: `POST /api/v1/agent/login`, `GET /api/v1/cards/uid/{uid}`.

## GET /products

Returns active products for the business unit, ordered by `display_order` then `code`. The POS shows this
list as the product dropdown before capturing a subscription and caches it for offline use.

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "code": "MICRO_EPARGNE_RETRAITE",
      "display_order": 1,
      "name_fr": "Micro Épargne Retraite",
      "name_en": "Micro Retirement Savings",
      "visa_number": "2021-08/MINIFID/SG/DGTCP/DA/SUNU Vie",
      "category": "Retraite",
      "guarantees_description": "Contrat individuel de micro-assurance sur la vie…",
      "default_premium_frequency": "monthly",
      "default_premium_amount": 5000,
      "policy_cost_amount": 1000,
      "sunulife_product_code": null
    }
  ]
}
```

`default_premium_amount` and `policy_cost_amount` are `null` until the back office agrees them.
`POST /proposals` accepts the chosen `insurance_product_id`; when it is absent the first active product is
used as a fallback, so the POS should always send it.
