# CNSS backend API (v1)

Reference for `POST /api/v1/cnss/voluntary-enrollments` and related behavior. Implementation: [`routes/api.php`](../routes/api.php), [`StoreCnssEnrollmentRequest`](../app/Http/Requests/Api/StoreCnssEnrollmentRequest.php), [`CnssController::storeEnrollment`](../app/Http/Controllers/Api/CnssController.php). Persisted rows use table **`cnss_voluntary_enrollments`** (model `CnssVoluntaryEnrollment`).

## Conventions

- **Character encoding:** Request bodies should use UTF-8. For voluntary enrollment, the server normalizes invalid byte sequences in `identification`, `other_information`, and `beneficiary` before persisting `form_data` (common with mobile multipart clients sending Latin-1 text).
- **Content-Type:** `application/json` for all CNSS API requests **except** voluntary enrollment **create**, which also accepts **`multipart/form-data`** (recommended for mobile: bracket-style fields + two JPEG file parts).
- **Auth:** Same API key / middleware as other `v1` routes (see project API auth).
- **Compatibility:** JSON (`Content-Type: application/json`) and simple form posts keep the **legacy minimal** field set. **Strict** rules (full identification, beneficiary, emergency contacts, both JPEG files) apply when the request **`Content-Type` is `multipart/form-data`** (mobile upload), not when only `Accept: application/json` is present.

## 1. Create voluntary enrollment

`POST /api/v1/cnss/voluntary-enrollments`

### 1a. Terminal / device identification

`device_serial` and `terminal_id` are resolved as **`terminals.serial_number`** for the given `business_unit_id`. The value must match an existing terminal row; an assigned agent is required. Prefer sending **`device_serial`** for clarity; `terminal_id` uses the same resolution path.

### 1b. Ledger `bank_account_number` vs declared value

The server creates a **ledger** account and sets `form_data.other_information.bank_account_number` to that **generated account number** (also exposed as `ledger_account_number` in the API response).

If the client sends a member / external account in `other_information.bank_account_number`, it is copied to **`form_data.other_information.declared_bank_account_number`** before the overwrite when non-empty, so declared banking data is not lost.

### 1c. Address mapping

`Client.home_address` is built from `identification.address` when present; otherwise from `identification.postal_address`, or a comma-joined fallback from `address_sector`, `district`, `department`, `province`, `postal_address` when those structured fields are sent (multipart).

### 1d. Beneficiary field names (mobile and legacy)

The API accepts **both** naming styles; stored `form_data.beneficiary` includes aligned keys:

| Mobile / multipart | Legacy / JSON alias |
| ------------------ | --------------------- |
| `dependent_children` | `children` |
| `beneficiary_address` | `address` |
| `spouse_full_name` | `spouse_name` |

`emergency_contact_name` and `emergency_contact_phone` are required for **multipart** enrollments and are also persisted on **`cnss_voluntary_enrollments`** (`emergency_contact_name`, `emergency_contact_phone`).

### 1e. Multipart wire format

`Content-Type: multipart/form-data; boundary=...`

Top-level fields: `business_unit_id` (required), `agent_id`, `terminal_id`, `device_serial` (optional per resolution rules).

Nested keys use bracket notation, e.g. `identification[last_name]`, `other_information[insurance_type_art]`, `beneficiary[emergency_contact_phone]`.

**Files (required for multipart):**

| Part name | Constraints |
| --------- | ----------- |
| `id_document` | JPEG, national ID **front**, max 4096 KB |
| `id_document_back` | JPEG, national ID **back**, max 4096 KB |
| `birth_certificate` | JPEG, max 4096 KB |

Files are stored under `storage/app/cnss/enrollments/{id}/` on the `local` disk; paths are saved on `cnss_voluntary_enrollments.id_document_path`, `id_document_back_path`, and `birth_certificate_path`.

### 1f. Equivalent JSON view (logical shape; JSON body uses nested objects)

See mobile contract for the full field list. Minimal **legacy JSON** example accepted by the server:

```json
{
  "business_unit_id": 1,
  "identification": {
    "last_name": "Ouedraogo",
    "first_names": "Jean Baptiste",
    "gender": "M",
    "date_of_birth": "1990-05-01",
    "phone": "+22670000000"
  },
  "other_information": {
    "branch_of_activity": "informal",
    "occupation": "Trader",
    "insurance_start_date": "2026-01-01",
    "payment_frequency": "monthly",
    "insurance_type_art": "4"
  },
  "beneficiary": {
    "father_name": "Father"
  }
}
```

When `other_information.insurance_type_art` is `5`, `other_information.existing_cnss_number` is **required**.

### 1g. Success response (shape)

`201` with JSON body including at least:

- `data.id`, `data.client_id`, `data.ledger_account_number`, voluntary/affiliation/worker numbers, dates, `status`
- `data.declared_bank_account_number` when supplied and different from ledger semantics
- `data.emergency_contact_name`, `data.emergency_contact_phone` when set
- `data.id_document_path`, `data.id_document_back_path`, `data.birth_certificate_path` when files were stored

`GET /api/v1/cnss/voluntary-enrollments/{id}?business_unit_id=` returns `form_data` when successful.

## 2. Other CNSS endpoints

See `routes/api.php` for members search and contributions; those remain JSON unless documented otherwise.
