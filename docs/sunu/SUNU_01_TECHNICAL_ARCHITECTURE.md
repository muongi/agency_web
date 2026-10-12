# SUNU Assurances Vie Burkina Faso
## Mobile Premium Collection Solution on POS Terminals
### Document 1 — Overall and Detailed Technical Architecture

| Field | Value |
|---|---|
| Document | Technical Architecture Specification |
| Version | 1.1 (adds §14 pilot as-built status; import formats in Document 5) |
| Date | 17 September 2026 (version 1.0: 31 August 2026) |
| Prepared for | SUNU Assurances Vie Burkina Faso — Central Operations Department |
| Prepared by | DBS SA — Digital Business Services |
| Source requirements | TdR "Acquisition d'une solution digitale de collecte de primes via TPE", 12 August 2026; SUNU requirements e-mail of 21 August 2026 (meeting of 20 August 2026) |
| Related documents | Doc 2 — Security Architecture & Procedures; Doc 3 — User Guide; Doc 4 — Deployment Plan; Doc 5 — Data Import and Exchange Formats |
| Status | For SUNU technical review ahead of the management meeting of 1 October 2026 |

---

## 1. Purpose and Scope

### 1.1 Purpose

This document describes the overall and detailed technical architecture of the Mobile Premium Collection Solution to be delivered to SUNU Assurances Vie Burkina Faso. It is the reference architecture against which the solution is built, tested, security-reviewed and accepted.

It answers point 2 of SUNU's requirements e-mail ("a document presenting the overall and detailed technical architecture of the solution") and point 1 ("integration with SUNULIFE and retrieval of existing data"), which is treated here as a first-class architectural concern rather than a later add-on.

Version 1.1 adds section 14, which records what the pilot running since September 2026 actually implements. Sections 1 to 13 remain the target architecture. File and data formats for importing existing customers are specified in Document 5.

### 1.2 Scope

**In scope**

- The Android POS application running on the field terminals (subscription capture, premium collection, receipt printing, card read/write, QR scan, Mobile Money initiation).
- The web back-office platform (user and profile management, insured and policy management, collections monitoring, collector caps and pay-ins, reporting and Excel export).
- The card issuance and personalisation chain (physical card + virtual QR card).
- The integration layer towards SUNULIFE, Mobile Money providers, and SMS.
- Hosting, network and deployment topology.

**Out of scope of this document** (covered elsewhere)

- Security controls and procedures — Document 2.
- End-user operating procedures — Document 3.
- Planning, milestones and responsibilities — Document 4.
- Commercial terms, hardware supply of terminals, cards and the card personalisation station — covered by the commercial offer.

### 1.3 Intended audience

SUNU IT and Operations, SUNU General Management (sections 2 and 3), the SUNULIFE editor or maintenance team (section 8), and the delivery team.

---

## 2. Business Context — What the System Must Do

The architecture is derived directly from the four needs stated in the TdR plus the four functional additions requested in the 21 August e-mail.

| Ref | Requirement | Origin | Architectural consequence |
|---|---|---|---|
| R1 | Subscribe a prospect to a micro-insurance policy and collect the first premium plus policy costs in the field | TdR Need 1 | Subscription capture module on POS; document/photo capture; geolocation; two distinct receipts |
| R2 | Photograph the signed proposal and the CNIB, transmit to back office with agent reference, product, proposal number, insured name, GPS | TdR Need 1 | Image pipeline, offline queue, metadata binding, back-office review queue |
| R3 | Issue a card carrying the insured's identity and his/her list of policies; support several policies per insured | TdR Need 1 | Card issuance workflow; multi-policy card data model; personalisation export to the card printer/encoder |
| R4 | Collect recurring premiums: read card → list active policies → select policy → collect → print receipt | TdR Need 2 | Card read flow on POS; policy selection screen; receipt template; local cache for offline operation |
| R5 | Per-collector collection cap (uncollected amount); when reached, the collector pays in to a SUNU bank account or cash office; back office confirms and releases the terminal | TdR Need 2 | Collector float ledger; cap engine; pay-in declaration + back-office confirmation; terminal release |
| R6 | All payments visible in the back office and downloadable in Excel for recording in SUNULIFE | TdR Need 2 | Collections reporting module and export; this is the *manual* fallback path to SUNULIFE |
| R7 | 15 POS terminals, back office with profiles and permissions, 5 000 cards, a card personalisation device | TdR Need 3 | Terminal registry, RBAC, card stock management, personalisation station integration |
| R8 | APIs for automatic update of collections in the core business software | TdR Need 4 | SUNULIFE integration — see section 8 |
| R9 | **Regularly retrieve up-to-date customer data and policy information from SUNULIFE via API** | E-mail §1 | SUNULIFE integration is bidirectional and part of the base architecture, not phase 2 |
| R10 | **Physical card** | E-mail §4 | As R3 |
| R11 | **Virtual card as a QR code** | E-mail §4 | Signed QR credential, printable/sendable, offline-verifiable on the POS |
| R12 | **Mobile Money payments** | E-mail §4 | Payment gateway abstraction; Orange Money / Moov Money, direct or via aggregator |
| R13 | Technical architecture documentation | E-mail §2 | This document |
| R14 | Security documentation | E-mail §2 | Document 2 |
| R15 | User guide per profile | E-mail §3 | Document 3 |
| R16 | Initial deployment before end September 2026; production launch by November 2026 | E-mail §5 | Deployment plan and phasing — Document 4; drives the "reuse first" architectural stance in section 4 |

### 2.1 A note on why the September date is achievable

The solution is **not built from scratch**. It is a customisation of an existing, in-production agency-banking platform (Android POS application + Laravel web back office) that already implements: agents, terminals, terminal-to-agent assignment, business units, roles and permissions, clients, accounts, cards with issuance and status lifecycle, transactions with costs and commissions, activity/audit logging, SMS, reporting with Excel export, and a configurable core-banking integration layer.

Critically, the platform already contains a **voluntary social-insurance module** (CNSS voluntary insured enrolment and periodic contribution collection) whose data model is structurally identical to what SUNU needs: an enrolment record carrying agent, terminal, status, identity fields, a flexible JSON form payload and supporting documents, plus a contribution record carrying amount, reference period, payment frequency and a link back to both the enrolment and the financial transaction. SUNU's "insurance proposal → policy → premium" is the same shape as "enrolment → membership → contribution".

The September pilot is therefore a **domain adaptation of proven code**, not new construction. Section 4 states explicitly, per component, what is reused, what is extended and what is new.

---

## 3. Overall Architecture (Level 1)

### 3.1 Context view

<!-- figure: 01-system-context | Figure 1 — System context. Solid links carry field collection; dashed links are non-blocking, so an outage on any of them stops that channel and not the collection of premiums. | replace -->

```
                         ┌──────────────────────────────────────┐
                         │        SUNU Assurances Vie           │
                         │           Burkina Faso               │
                         └──────────────────────────────────────┘
                                          │
   ┌──────────────┐                       │                    ┌──────────────────┐
   │  Insured /   │                       │                    │    SUNULIFE      │
   │   Prospect   │                       │                    │ (core business   │
   └──────┬───────┘                       │                    │    software)     │
          │ signs proposal,               │                    └────────┬─────────┘
          │ pays premium,                 │                             │ REST / SOAP
          │ presents card or QR           │                             │ (section 8)
          ▼                               ▼                             ▼
   ┌────────────────┐   HTTPS    ┌─────────────────────────────────────────────┐
   │  POS TERMINAL  │◄──────────►│         BACK-OFFICE PLATFORM                │
   │  (Android)     │  REST/JSON │  Web application + REST API + database      │
   │  15 units      │            │                                             │
   │                │            │  • Identity, profiles, permissions          │
   │ • Subscription │            │  • Insured & policy repository              │
   │ • Premium coll.│            │  • Collections & receipts                   │
   │ • Card R/W     │            │  • Collector caps & pay-ins                 │
   │ • QR scan      │            │  • Card issuance & personalisation          │
   │ • Printer      │            │  • Reporting & Excel export                 │
   │ • Camera/GPS   │            │  • Integration hub                          │
   └────────────────┘            └───────┬──────────────┬──────────────┬───────┘
                                         │              │              │
                              ┌──────────▼───┐  ┌───────▼──────┐  ┌────▼───────┐
                              │ Mobile Money │  │  SMS gateway │  │    Card    │
                              │ Orange/Moov  │  │              │  │personalisa-│
                              │ or aggregator│  │              │  │tion station│
                              └──────────────┘  └──────────────┘  └────────────┘
                                         │
                              ┌──────────▼────────────────────────┐
                              │ SUNU back-office users:            │
                              │ Back-Office Agent, Supervisor,     │
                              │ Card Officer, Administrator, IT    │
                              └────────────────────────────────────┘
```

### 3.2 Architectural principles

1. **Field-first, offline-tolerant.** A collector in a market or a village must be able to work when the network is poor. Every field operation writes locally first, produces a printed receipt immediately, and synchronises afterwards. Loss of connectivity never loses a collection.
2. **The back office is the system of record for collection; SUNULIFE remains the system of record for the contract.** The solution never invents a policy. It either receives it from SUNULIFE (R9) or creates a *proposal* that becomes a policy once SUNULIFE confirms. This prevents two conflicting truths about a client's contract.
3. **Single financial spine.** Cash, Mobile Money and card-identified collections all produce the same `transaction` record, differing only by payment instrument. Reporting, caps, commissions and the SUNULIFE push therefore have one code path.
4. **Integration behind adapters.** SUNULIFE, each Mobile Money provider and the SMS gateway sit behind an internal interface. Changing provider, or SUNULIFE changing its API, is a configuration and adapter change, not a redesign. The platform already applies this pattern for core-banking systems (configurable driver + per-institution credentials + field mapping), and SUNULIFE reuses it.
5. **Everything attributable.** Every subscription, collection, pay-in and card issuance carries agent, terminal, timestamp and — where the device permits — GPS coordinates. This is both a TdR requirement and the foundation of fraud control.
6. **Degrade, don't stop.** If SUNULIFE is unreachable, collection continues and the Excel export of TdR Need 2 remains available. If Mobile Money is down, cash collection continues. No external dependency can halt premium collection.

### 3.3 Technology stack

| Layer | Technology | Rationale |
|---|---|---|
| POS application | Android (Java), min SDK 21; pilot terminal Telpo F20 (Telpo / Feitian POS hardware) | Matches the terminals in the TdR; existing production codebase; native access to NFC reader, thermal printer, camera, GPS |
| POS local storage | SQLite | Offline queue and local caches |
| Back-office application | PHP 8 / Laravel 9, Blade UI | Existing production codebase; rapid customisation; large maintenance pool in the region |
| Database | MySQL 8 (InnoDB) | Transactional integrity, proven at this scale |
| API style | REST / JSON over HTTPS | Simplicity, debuggability, POS friendliness |
| Async processing | Laravel queues (database driver, Redis optional) | SUNULIFE sync, SMS, exports, image processing off the request path |
| Reporting/export | Server-side generation to XLSX/CSV | TdR Need 2 explicitly requires Excel |
| Card | Contactless/NFC card, and magnetic-stripe personalisation as specified by the TdR | Section 7 |
| Virtual card | Signed QR credential (JWS/HMAC) | Section 7.3 |
| Hosting | Linux VPS/VM. Pilot: Apache HTTP Server + PHP. Production target: Nginx + PHP-FPM behind TLS | Sections 10 and 14 |

---

## 4. Component Architecture (Level 2) — Reuse, Extend, Build

This is the heart of the delivery plan: it states honestly, per component, what already exists.

<!-- figure: 02-component-map | Figure 2 — Component map. Teal is reused unchanged, amber is extended, rust is new build. New build is 9 of 33 components. -->

Legend: **[R]** reused as-is · **[E]** existing component extended · **[N]** new build

### 4.1 Back-office platform

| # | Component | Status | Detail |
|---|---|---|---|
| B1 | Authentication & session management | [R] | Web login, session lifetime, password hashing |
| B2 | Organisation & business units | [R] | Multi-entity model; SUNU Vie Burkina Faso is a business unit; branches modelled as units |
| B3 | Users, roles, permissions | [R] | Granular permission model with global and per-unit roles. Satisfies TdR Need 3 "create profiles for collectors and other users, as well as manage permissions" |
| B4 | Collection agents (collectors) | [E] | Agent registry with identity, photo, username, credentials, per-unit scoping. Extended with collector cap parameters (R5) |
| B5 | Terminal registry & agent–terminal assignment | [R] | Serial number, SIM, alias, model, assignment history. Satisfies the 15-terminal fleet management need |
| B6 | Insured (client) repository | [E] | Existing client entity with identity, documents, groups. Extended with SUNULIFE identifiers and insurance-specific attributes |
| B7 | Product catalogue (insurance products) | [N] | Micro-Quiétude, Micro Épargne Retraite and future products: code, label, premium periodicity, policy-cost rules, receipt template |
| B8 | Insurance proposal (subscription) | [E] | Adapted from the existing voluntary-enrolment module: proposal number, product, agent, terminal, GPS, status workflow, attached photographs of the proposal and CNIB, flexible JSON form payload |
| B9 | Policy (contract) repository | [N] | Policy number, product, insured, status, effective date, premium amount and frequency, SUNULIFE reference. Populated from SUNULIFE (R9) or on confirmation of a proposal |
| B10 | Premium collection engine | [E] | Adapted from the existing periodic-contribution module: amount, reference period, frequency, unique reference, link to policy, agent, terminal and financial transaction |
| B11 | Financial transaction spine | [R] | Amount, cost, commission, business share, date, status, unique reference, JSON metadata, full attribution. Already carries per-transaction cost and commission configuration with flat/percentage/tiered rules |
| B12 | Collector cap & pay-in reconciliation | [N] | Cap per collector; running uncollected balance; pay-in declaration (bank deposit or cash office); back-office confirmation; terminal release |
| B13 | Card management & issuance | [E] | Card entity with number, prefix, status lifecycle (active/inactive/blocked/expired/replaced/unassigned), expiry, limits, issuance and activation timestamps, block reason and replacement chain; per-unit card settings (prefix, sequence length, default validity). Extended for multi-policy content and personalisation export |
| B14 | Virtual card (QR) issuance | [N] | Section 7.3 |
| B15 | Mobile Money gateway adapter | [N] | Section 9 |
| B16 | SUNULIFE integration hub | [N] | Section 8. Built on the existing configurable external-system driver pattern (driver definition + per-unit credentials + request/response field mapping + auth strategies including JWT bearer, basic and OAuth password) |
| B17 | Collections reporting & Excel export | [E] | Existing asynchronous report generation with status tracking, download and retry. Extended with the insurance-specific collections report required by TdR Need 2 |
| B18 | Dashboards | [E] | Existing per-unit dashboards extended with collection KPIs, cap utilisation and field activity map |
| B19 | Audit trail / activity log | [R] | Records actor, event, subject, before/after values, batch grouping |
| B20 | API request logging | [R] | Dedicated logging middleware already exists for transaction, card and OTP API traffic |
| B21 | SMS notifications | [R] | Configurable provider, per-unit sender, credit management, message log |
| B22 | Bilingual interface (FR/EN) | [R] | Runtime locale switching with French translations already in place — mandatory for Burkina Faso |

### 4.2 POS application

| # | Component | Status | Detail |
|---|---|---|---|
| P1 | Splash, login, session | [R] | Agent authentication against the back office; inactivity timeout |
| P2 | Home dashboard & navigation | [E] | Rebuilt around the insurance menu: Subscribe, Collect Premium, Pay-In, Reports, Settings |
| P3 | Document & ID capture | [E] | Camera capture of the signed proposal and the CNIB (front/back), compression, local queue, upload with metadata |
| P4 | Geolocation capture | [E] | GPS coordinates attached to subscription and collection |
| P5 | Subscription workflow (stepper) | [E] | Adapted from the existing multi-step enrolment stepper |
| P6 | Card read (NFC) | [E] | Existing card-read activity; extended to return the insured's list of active policies |
| P7 | Card write / personalisation | [E] | Existing card-write activity; extended for multi-policy payload |
| P8 | QR virtual card scan | [N] | Camera-based scan and offline signature verification |
| P9 | Policy selection & premium entry | [N] | Screen listing active contracts for the identified insured |
| P10 | Payment instrument selection | [N] | Cash / Mobile Money |
| P11 | Mobile Money collection | [N] | Initiation, status polling, timeout and reversal handling |
| P12 | Receipt printing | [E] | Existing thermal printer integration; new templates for policy costs, first premium and recurring premium |
| P13 | Offline queue & synchronisation | [E] | Local SQLite store; retry with backoff; conflict-free unique references |
| P14 | Collector cap indicator & pay-in declaration | [N] | Live uncollected balance, warning threshold, blocked state, pay-in submission |
| P15 | Agent reports on device | [E] | Day's collections, totals, re-print of a receipt |
| P16 | Bilingual FR/EN | [R] | Already implemented |
| P17 | Biometric capture (fingerprint) | [R] | Present in the platform; **optional** for SUNU, available if SUNU wishes to authenticate the insured biometrically instead of, or in addition to, the card |

### 4.3 Effort concentration

New build is concentrated in five places: the insurance product/policy model (B7, B9), collector caps and pay-ins (B12, P14), the QR virtual card (B14, P8), Mobile Money (B15, P10–P11), and the SUNULIFE hub (B16). Everything else is adaptation of running code. Document 4 sequences these.

---

## 5. Data Architecture

### 5.1 Core entities

```
business_unit
   │
   ├── user ──── role ──── permission
   │
   ├── agent (collector) ──┬── agent_terminal ──── terminal
   │                       └── collector_cap / collector_balance      [new]
   │
   ├── client (insured) ───┬── client_document (proposal photo, CNIB)
   │                       ├── proposal              [adapted]
   │                       ├── policy                [new]
   │                       └── card ────── card_policy_link           [new]
   │                                └── virtual_card_credential       [new]
   │
   ├── insurance_product        [new]
   │
   ├── premium_collection ─── transaction ─── transaction_type
   │        [adapted]              [reused]
   │
   ├── pay_in ──── pay_in_lines  [new]
   │
   ├── momo_payment              [new]
   │
   ├── sunulife_sync_log         [new]
   │
   ├── report (async export)     [reused]
   └── activity_log (audit)      [reused]
```

### 5.2 Key new/adapted tables

**`insurance_product`** — `code`, `name_fr`, `name_en`, `family` (micro-insurance / standard), `default_premium_frequency`, `policy_cost_rule`, `receipt_template`, `sunulife_product_code`, `is_active`.

**`proposal`** — `business_unit_id`, `agent_id`, `terminal_id`, `client_id`, `insurance_product_id`, `proposal_number` (from the physical form, unique per unit), `status` (captured → transmitted → under review → accepted → rejected), `subscribed_at`, `latitude`, `longitude`, `form_data` (JSON), `proposal_image_path`, `id_document_front_path`, `id_document_back_path`, `sunulife_policy_number` (filled on acceptance), timestamps. *Directly mirrors the existing enrolment table, which already carries agent, terminal, status, identity fields, `form_data` JSON and document paths.*

**`policy`** — `business_unit_id`, `client_id`, `insurance_product_id`, `policy_number`, `sunulife_policy_id`, `status` (active / suspended / lapsed / terminated), `effective_date`, `premium_amount`, `premium_frequency`, `next_due_date`, `outstanding_balance`, `source` (`sunulife` | `local_proposal`), `synced_at`. Unique on (`business_unit_id`, `policy_number`).

**`premium_collection`** — `business_unit_id`, `policy_id`, `proposal_id` (for a first premium), `agent_id`, `terminal_id`, `transaction_id`, `amount`, `collection_kind` (`policy_cost` | `first_premium` | `recurring_premium`), `reference_period`, `payment_frequency`, `payment_instrument` (`cash` | `momo` | `bank`), `reference` (unique, generated on the device), `latitude`, `longitude`, `collected_at`, `receipt_number`, `metadata` (JSON), `sunulife_pushed_at`.

**`collector_cap`** — `agent_id`, `cap_amount`, `warning_threshold_pct`, `currency`, `is_active`.
**`collector_balance`** — `agent_id`, `uncollected_amount`, `last_pay_in_at`, `is_blocked`, `blocked_at`. Maintained transactionally on every collection and every confirmed pay-in.

**`pay_in`** — `agent_id`, `declared_amount`, `method` (`bank_deposit` | `cash_office`), `bank_reference`, `deposit_slip_path`, `declared_at`, `status` (`declared` | `confirmed` | `rejected`), `confirmed_by`, `confirmed_at`, `confirmed_amount`, `variance`, `notes`. Confirmation releases the terminal.

**`card_policy_link`** — `card_id`, `policy_id`, `slot_index`. Supports the TdR's explicit requirement that a card retain several policies for one insured.

**`virtual_card_credential`** — `client_id`, `card_id` (nullable), `credential_id`, `payload_hash`, `signature`, `key_version`, `issued_at`, `expires_at`, `revoked_at`.

**`momo_payment`** — `premium_collection_id`, `provider`, `provider_reference`, `msisdn`, `amount`, `status`, `initiated_at`, `finalised_at`, `callback_payload` (JSON), `reconciliation_status`.

**`sunulife_sync_log`** — `direction` (`pull` | `push`), `entity`, `entity_id`, `request_payload`, `response_payload`, `http_status`, `status`, `attempts`, `last_error`, `synced_at`.

### 5.3 Idempotency and reference generation

Every field-originated operation carries a **client-generated unique reference** built from terminal serial + agent id + monotonic counter + timestamp. The back office enforces uniqueness on that reference. A retried upload after a network timeout therefore cannot create a double collection. This is the single most important data-integrity control in an offline-tolerant collection system, and it is enforced at the database level, not only in application code.

### 5.4 Data retention

| Data | Retention | Note |
|---|---|---|
| Financial transactions and collections | 10 years | Insurance/accounting retention |
| Proposal and identity images | 10 years, archived to cold storage after 2 years | Volume control |
| Audit trail | 5 years | |
| API request logs | 12 months | |
| Biometric templates (if enabled) | Life of the relationship + 1 year | See Document 2 |
| Session and technical logs | 90 days | |

---

## 6. Functional Flows (Level 3)

### 6.1 Flow A — Subscription and first premium (TdR Need 1)

```
COLLECTOR                    POS TERMINAL                  BACK OFFICE            SUNULIFE
    │                             │                             │                     │
    │ fills paper proposal        │                             │                     │
    │ client signs                │                             │                     │
    │                             │                             │                     │
    ├─ opens "New subscription" ─►│                             │                     │
    │                             │ selects product             │                     │
    │                             │ enters proposal number      │                     │
    │                             │ enters insured identity     │                     │
    │                             │ photographs proposal        │                     │
    │                             │ photographs CNIB (2 sides)  │                     │
    │                             │ captures GPS                │                     │
    │                             │ ── stores locally ────────► │                     │
    │                             │                             │                     │
    ├─ collects policy costs ────►│ prints RECEIPT #1 (costs)   │                     │
    ├─ collects first premium ───►│ prints RECEIPT #2 (premium) │                     │
    │                             │                             │                     │
    │                             │ ── uploads when online ───► │ creates proposal    │
    │                             │                             │ + 2 collections     │
    │                             │                             │ + 2 transactions    │
    │                             │                             │                     │
    │                             │                             │ back-office review  │
    │                             │                             │ ── push proposal ──►│
    │                             │                             │ ◄── policy number ──┤
    │                             │                             │ creates policy      │
    │                             │                             │ queues card issue   │
```

**Data transmitted with the proposal, per the TdR:** collection agent's references, product subscribed to, proposal number shown on the physical document, insured's last name and first name, GPS coordinates of the subscription location.

**Data transmitted with each payment, per the TdR:** product name, proposal number, collection agent's references, amount collected, date, time, and GPS coordinates where available.

The two receipts are distinct documents with distinct numbers, as the TdR requires a receipt for policy costs and a separate receipt for the first premium.

### 6.2 Flow B — Recurring premium collection by card (TdR Need 2)

```
INSURED         COLLECTOR        POS TERMINAL              BACK OFFICE
   │                │                  │                        │
   ├─ presents card►│                  │                        │
   │                ├─ taps card ─────►│ reads card UID         │
   │                │                  ├─ lookup by UID ───────►│
   │                │                  │◄── insured + active ───┤
   │                │                  │    policies list       │
   │                │                  │                        │
   │                │◄─ policy list ───┤  (cached locally for   │
   │                │                  │   offline operation)   │
   │                ├─ selects policy ►│                        │
   │                ├─ enters amount ─►│ validates vs premium   │
   │                ├─ chooses cash    │                        │
   │                │   or Mobile Money│                        │
   │                │                  │ [if MoMo → section 9]  │
   │                │                  │                        │
   │◄── RECEIPT ────┼──────────────────┤ prints receipt         │
   │                │                  ├─ posts collection ────►│ collection +
   │                │                  │                        │ transaction
   │                │                  │                        │ collector balance +
   │                │                  │◄─ cap status ──────────┤ cap check
```

If the terminal is offline, the policy list is served from the local cache (refreshed on each successful sync), the receipt is printed immediately, and the collection is queued. The insured is never turned away because of the network.

### 6.3 Flow C — Collector cap and pay-in (TdR Need 2)

```
Each collection: collector_balance.uncollected_amount += amount

  uncollected < warning threshold      → normal operation
  warning ≤ uncollected < cap          → POS shows a warning banner on every screen
  uncollected ≥ cap                    → POS blocks NEW COLLECTIONS
                                          (subscription capture still allowed)

COLLECTOR                     POS               BACK OFFICE            SUPERVISOR
    │                          │                     │                      │
    ├─ deposits at bank OR ────┼─────────────────────┼──────────────────────┤
    │  hands cash to cash office                     │                      │
    ├─ declares pay-in ───────►│ amount, method,     │                      │
    │                          │ bank ref, photo of  │                      │
    │                          │ deposit slip ──────►│ pay_in (declared)    │
    │                          │                     ├─ notifies ──────────►│
    │                          │                     │                      │
    │                          │                     │◄── confirms amount ──┤
    │                          │                     │  (or rejects with    │
    │                          │                     │   variance noted)    │
    │                          │                     │ balance -= confirmed │
    │                          │◄── terminal released┤ is_blocked = false   │
    ├─ resumes collection ────►│                     │                      │
```

Confirmation is a **controlled action**: it is permission-gated, fully audited, and can be configured to require two-person approval (maker–checker) above a configurable amount. See Document 2, section on segregation of duties.

### 6.4 Flow D — Card issuance (TdR Need 1 and Need 3)

```
BACK OFFICE (proposal accepted)
   │
   ├─ creates policy
   ├─ allocates a card from stock (status: unassigned → inactive)
   ├─ links policy/policies to the card
   ├─ generates the PERSONALISATION FILE:
   │     • SUNU Assurances Vie branding block
   │     • insured last name and first name
   │     • card number
   │     • expiry
   │     • encoded data: card UID ↔ insured reference ↔ policy list
   │
   ▼
CARD OFFICER at the personalisation station
   ├─ prints the personalised card
   ├─ encodes the card
   ├─ marks the card as WRITTEN in the back office
   ├─ hands/dispatches the card to the insured
   ▼
   card status → active
```

The card carries an **identifier, not money and not sensitive personal data**. The authoritative policy list always lives in the back office; what is written on the card lets a terminal identify the insured quickly and work offline. Rationale and threat analysis in Document 2, section 7.

### 6.5 Flow E — SUNULIFE synchronisation

See section 8.

---

## 7. Card Architecture (physical and virtual)

### 7.1 Physical card

- **Body:** SUNU-branded card, personalised with SUNU Assurances Vie information and the insured's last name and first name, as required by the TdR.
- **Numbering:** back office generates the card number from a per-unit prefix and a sequence of configurable length; expiry derived from the configured default validity (existing card-settings mechanism).
- **Data written:** card serial/UID, insured reference, and the list of policy slots. Multi-policy support is explicit in the TdR ("if the insured has several policies, we would like the card to be able to retain this information").
- **Lifecycle:** unassigned → inactive → active → (blocked | expired | replaced). Blocking records a reason and a timestamp; replacement records the chain to the new card. All already implemented.
- **Stock:** the 5 000 cards are managed as stock in the back office; each card moves from unassigned to assigned when linked to an insured.

### 7.2 Card technology decision (open point OP-3)

The TdR says "magnetic card". Two options are on the table and we recommend the second:

| | Magnetic stripe | Contactless / NFC |
|---|---|---|
| Cost per card | Lower | Slightly higher |
| Durability in field conditions | Poor — stripe wears, demagnetises | Good |
| Multi-policy storage on card | Very limited (max ~100 characters usable) | Ample |
| Security | Trivially cloneable, no authentication | Supports mutual authentication and per-card keys |
| POS support on the specified terminals | Yes | Yes — the terminals already integrate an NFC reader in the existing solution |
| Recommendation | | **Preferred** |

**Recommendation:** contactless cards. Magnetic stripe cannot hold a meaningful multi-policy payload and offers no protection against cloning. If SUNU prefers hybrid cards (printed + stripe + chip), the architecture supports it; the encoded content and the personalisation file are the same.

### 7.3 Virtual card — QR code (e-mail §4)

A virtual card is a **signed credential**, not a picture of a number.

```
QR payload (compact, base64url):
{
  "v":   1,                       schema version
  "cid": "SUNU-BF-00012345",      credential id
  "ins": "CLI-000987",            insured reference
  "nm":  "OUEDRAOGO Awa",         name for on-screen confirmation
  "pol": ["P-2026-0043","P-2026-0044"],   policy references
  "iat": 1756598400,              issued at
  "exp": 1788134400,              expiry
  "kid": "sunu-2026-k1"           signing key version
}
+ detached signature (HMAC-SHA256 or ECDSA P-256)
```

Properties:

1. **Offline-verifiable.** The POS holds the public key (or the shared HMAC key in the secure store) and validates the signature without network access. A forged QR is rejected on the device.
2. **Revocable.** A revocation list is pushed to terminals on each sync. Revoking a virtual card is immediate in the back office and effective on the terminal at its next sync.
3. **Time-bounded.** Credentials expire and are re-issued, limiting the window of a leaked QR.
4. **Non-monetary.** The QR identifies the insured; it authorises nothing. Payment still requires the collector's authenticated session, and optionally the insured's PIN or biometric where SUNU chooses to enable it.
5. **Distribution.** Printed on the policy schedule, sent by SMS link, or displayed in a future insured-facing app. No hardware needed — this is what makes it the cheap channel for an insured who has lost a card or lives far from a personalisation point.

Physical card and virtual card resolve to the **same insured identity**; the POS treats them as two readers onto one lookup.

---

## 8. SUNULIFE Integration Architecture (E-mail §1 — priority requirement)

SUNU stated that a significant share of its customers already exists in SUNULIFE and asked that the solution regularly retrieve up-to-date customer data and policy information via API, and that this be taken into account from the architecture design stage. This section is the architectural answer and, equally, the **list of what we need from SUNU** to build it.

### 8.1 Integration principles

1. **SUNULIFE is the master for insured identity and contracts.** The solution mirrors, it does not own.
2. **The solution is the master for field collection events.** SUNULIFE receives them.
3. **No blocking coupling.** SUNULIFE being unavailable degrades freshness, never availability of collection.
4. **Every exchange is logged and replayable.** `sunulife_sync_log` records request, response, status and attempts; any failed item can be replayed without duplicating a financial event (idempotency keys, section 5.3).
5. **The Excel export remains permanently available** as the contractual fallback of TdR Need 2. Automation removes manual work; it does not remove the manual option.

### 8.2 Integration surface — what we need from SUNULIFE

| # | Direction | Capability | Purpose | Frequency |
|---|---|---|---|---|
| S1 | Pull | **Customer search / lookup** by national ID (CNIB), policy number, phone, or customer number | Field identification of an existing insured; avoid duplicate creation | On demand + nightly delta |
| S2 | Pull | **Customer master delta** — customers created or modified since a timestamp | Keep the local mirror fresh | Nightly (configurable) |
| S3 | Pull | **Policy list for a customer** — policy number, product, status, effective date, premium amount, frequency, next due date, outstanding balance | Populate the card and the POS policy-selection screen | Nightly delta + on demand |
| S4 | Pull | **Product catalogue** — product codes and labels | Keep product references aligned | Weekly |
| S5 | Push | **Premium collection notification** — policy number, amount, value date, receipt number, collector reference, payment instrument, unique idempotency key | Automatic update of collections (TdR Need 4) | Near-real-time, queued |
| S6 | Push | **New subscription / proposal** — proposal number, product, insured identity, first premium, policy costs, documents | Create the contract in SUNULIFE and return the policy number | On back-office acceptance |
| S7 | Pull | **Acknowledgement / posting status** for a pushed collection | Reconciliation; proves the collection landed | Polled or via callback |

### 8.3 Synchronisation model

<!-- figure: 03-sunulife-integration | Figure 3 — SUNULIFE integration and its phasing. Phase 0 carries the September pilot on a file extract and the Excel export; the API phases in behind the same adapter afterwards. | replace -->

```
                    ┌──────────────────────────────────────┐
                    │           SUNULIFE                    │
                    └───────┬───────────────────▲──────────┘
                     PULL   │                   │   PUSH
        ┌───────────────────▼───────┐   ┌───────┴────────────────────┐
        │  Scheduled sync workers    │   │  Outbound event queue      │
        │  • customer delta (nightly)│   │  • collection posted       │
        │  • policy delta  (nightly) │   │  • proposal accepted       │
        │  • product cat.  (weekly)  │   │  retry: 1m,5m,15m,1h,6h,24h│
        │  • on-demand lookup        │   │  then manual review queue  │
        └───────────────┬────────────┘   └───────┬────────────────────┘
                        │                        │
                    ┌───▼────────────────────────▼───┐
                    │   SUNULIFE ADAPTER              │
                    │   • auth strategy               │
                    │   • field mapping (configurable)│
                    │   • transform & validate        │
                    │   • idempotency keys            │
                    │   • circuit breaker             │
                    │   • full request/response log   │
                    └───┬────────────────────────────┘
                        │
                    ┌───▼──────────────────────────┐
                    │  Local mirror: client, policy │
                    │  + local: proposal, collection│
                    └───────────────────────────────┘
```

**Reuse note.** The platform already implements exactly this adapter pattern for core banking systems: a configurable external-system definition with base URL, authentication strategy (JWT bearer, HTTP basic, OAuth password), per-institution credentials held separately, and per-transaction-type **request and response field mappings with transforms**. SUNULIFE becomes a driver in that framework. This is why S1–S7 can be delivered quickly *once the API is documented* — the plumbing exists.

**Conflict rules.**
- Customer or policy field differs between mirror and SUNULIFE → SUNULIFE wins, previous value written to the audit trail.
- A locally created insured is later matched to a SUNULIFE customer (by CNIB or phone) → records are merged, local reference retained as an alias, collections re-pointed.
- A collection pushed to SUNULIFE and rejected → collection stays valid and reconciled locally, flagged in a back-office exception queue for the Back-Office Agent to resolve. Money already received is never reversed by an integration failure.

### 8.4 Phasing (this is the pragmatic path to the September date)

| Phase | Content | Target |
|---|---|---|
| **Phase 0 — now** | Excel export for manual recording into SUNULIFE, exactly as TdR Need 2 specifies. Bulk **one-off import** of the existing customer and policy base from a SUNULIFE extract (CSV file, format in Document 5) so field agents immediately see existing customers | Pilot, September 2026 |
| **Phase 1** | Read-only integration: S1, S3 on demand; S2, S4 scheduled delta | Production, November 2026 |
| **Phase 2** | Write integration: S5, S6, S7 | Post-launch, per TdR Need 4 |

Phase 0 matters: it means **SUNU's requirement that agents see existing customers is met at the pilot even if the API is not yet available**, via a periodic extract. That removes SUNULIFE from the critical path of the September deadline while still honouring the intent of the requirement.

### 8.5 Information required from SUNU / the SUNULIFE editor

This is the questionnaire to be answered before or at the meeting of 1 October 2026. Each unanswered line is a scheduling risk.

**Technical**
1. Does SUNULIFE expose an API today? REST/JSON, SOAP/XML, or database/file-based only?
2. Is there API documentation, a WSDL/OpenAPI file, or a Postman collection?
3. Which authentication does it use — API key, OAuth 2.0, JWT, mutual TLS, IP allow-list?
4. Is SUNULIFE hosted on-premises at SUNU, at the SUNU group, or by the editor? In which country?
5. Can it be reached from our hosting environment, or is a VPN / private link required?
6. Are there rate limits or maintenance windows?
7. Is there a test/UAT instance, and when can access be granted?

**Functional**
8. What uniquely identifies a customer in SUNULIFE — customer number, CNIB, or a composite?
9. What is the exact policy status vocabulary (active, lapsed, suspended, terminated…)?
10. What are the product codes for Micro-Quiétude and Micro Épargne Retraite?
11. For a premium payment, which fields does SUNULIFE require to post a receipt correctly?
12. Does SUNULIFE return a posting confirmation/receipt reference we can store?
13. Approximate volumes: number of customers, number of active policies, expected daily collections?

**Organisational**
14. Who is the SUNULIFE technical contact (editor or internal team)?
15. Is a change request or contractual authorisation needed from the editor to open the API, and what is its lead time?
16. Who validates the data mapping on SUNU's side?

**If the answer to question 1 is "no API exists":** the architecture falls back to a documented **file-based exchange** — scheduled secure file drop (SFTP) of extracts in, and collection files out, in a format agreed with SUNU. This is fully supported and does not change any other part of the design; it only changes the adapter.

---

## 9. Mobile Money Architecture (e-mail §4)

### 9.1 Provider-agnostic design

The solution does not hard-code a Mobile Money operator. A single internal interface is implemented by one adapter per provider:

```
   POS  ──►  BACK OFFICE  ──►  PaymentGateway (interface)
                                    │
              ┌─────────────────────┼─────────────────────┐
              ▼                     ▼                     ▼
       OrangeMoneyAdapter    MoovMoneyAdapter     AggregatorAdapter
                                                  (one contract,
                                                   both operators)
```

Operations: `initiateCollection`, `checkStatus`, `handleCallback`, `reconcile`, `refund` (where the provider supports it).

### 9.2 Collection flow

```
Collector selects "Mobile Money" → enters the insured's MSISDN
   │
   ├─► back office creates momo_payment (status: initiated) + idempotency key
   ├─► adapter calls the provider's collection API
   │
   ├─► insured receives a USSD/push prompt and enters the PIN on his/her own phone
   │
   ├─► provider callback  ──►  back office (signature verified, source IP checked)
   │       OR  status polling if no callback is available
   │
   ├─ SUCCESS → premium_collection + transaction created, receipt printed,
   │            collector balance NOT increased (money went to SUNU, not to the collector)
   ├─ FAILED  → nothing financial recorded, collector may retry or take cash
   └─ TIMEOUT → payment left "pending", resolved by the reconciliation job;
                the receipt is printed only after confirmation
```

**Design point that matters operationally:** a Mobile Money premium does **not** increase the collector's uncollected balance, because the collector never holds that cash. Only cash collections consume the cap. This makes Mobile Money the natural relief valve when a collector approaches the cap far from a bank — a direct operational benefit to SUNU.

### 9.3 Direct versus aggregator

| Criterion | Direct (Orange Money + Moov Money) | Aggregator |
|---|---|---|
| Integrations to build | 2 | 1 |
| Commercial contracts | 2, each with its own onboarding lead time | 1 |
| Per-transaction cost | Operator rate only | Operator rate + aggregator margin |
| Settlement | Directly into SUNU's operator wallets/accounts | Through the aggregator, one settlement stream |
| Time to first transaction | Longer — operator onboarding is the constraint | Shorter |
| Coverage of both operators | Yes | Yes |
| Dependency risk | Spread | Concentrated on the aggregator |

**Recommendation given the timetable:** start with an aggregator to secure the November production launch, keeping the adapter interface so that migrating to direct operator integrations later is an adapter swap and a settlement change, not a redesign. SUNU chooses; the architecture supports either, and both, simultaneously.

**Decision needed from SUNU (OP-4):** which route, and which entity holds the merchant account. Onboarding lead time with the operators is the single largest external risk to the November date — see Document 4, risk register.

### 9.4 Reconciliation

A daily job compares, per provider: transactions recorded by the platform, the provider's settlement report, and the amounts credited to SUNU's account. Discrepancies are raised in an exception queue with an ageing indicator. No Mobile Money collection is considered final until it appears in the provider's settlement file.

---

## 10. Deployment and Infrastructure Architecture

### 10.1 Environments

| Environment | Purpose | Data | Access |
|---|---|---|---|
| **Development** | Build | Synthetic | Delivery team |
| **UAT / Recette** | SUNU acceptance testing, training | Anonymised or synthetic | SUNU testers, trainers, delivery team |
| **Production** | Live collection | Real | Restricted, audited |

A UAT environment available to SUNU testers is a prerequisite for the end-September pilot; it is the environment on which training in Document 3 is delivered.

### 10.2 Production topology

<!-- figure: 04-deployment-topology | Figure 4 — Deployment topology. One TLS and rate-limiting boundary in front, the database reachable only from inside the trust boundary, and every outbound integration allow-listed. | replace -->

```
                        Internet
                            │
                    ┌───────▼────────┐
                    │  TLS termination│  HTTPS only, TLS 1.2+
                    │  + WAF / rate   │  HSTS
                    │    limiting     │
                    └───────┬────────┘
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
 ┌──────▼──────┐    ┌───────▼───────┐   ┌──────▼───────┐
 │ Web / API    │    │ Queue workers │   │ Scheduler    │
 │ Nginx +      │    │ (sync, SMS,   │   │ (cron: delta │
 │ PHP-FPM      │    │  exports,     │   │  sync, recon,│
 │              │    │  images)      │   │  backups)    │
 └──────┬───────┘    └───────┬───────┘   └──────┬───────┘
        └────────────────────┼──────────────────┘
                    ┌────────▼─────────┐
                    │  MySQL 8          │
                    │  + daily backup   │
                    │  + binlog / PITR  │
                    └────────┬──────────┘
                             │
                    ┌────────▼──────────┐
                    │ Object/file store  │
                    │ proposal & ID      │
                    │ images, exports    │
                    │ (encrypted)        │
                    └────────────────────┘

  Outbound (allow-listed): SUNULIFE · Mobile Money · SMS gateway
  Inbound  (allow-listed): POS terminals · SUNU office network · MoMo callbacks
```

### 10.3 Sizing for the pilot and the first year

| Parameter | Pilot (Sept 2026) | Year 1 |
|---|---|---|
| Terminals | 15 | 50–100 |
| Collectors | ~15 | ~100 |
| Back-office users | 5–10 | 20–30 |
| Collections/day | 300–1 500 | up to 10 000 |
| Cards | 5 000 | 20 000+ |
| Images stored/day | ~50 subscriptions × 3 images | ~500 × 3 |

The pilot runs comfortably on a single application server (4 vCPU / 8 GB) with a separate database server (4 vCPU / 16 GB) and ~500 GB of storage. The topology above scales horizontally by adding application and worker nodes behind the TLS layer without architectural change.

### 10.4 Hosting location (open point OP-5)

Decision required from SUNU: hosting in Burkina Faso, elsewhere in the UEMOA zone, or in Europe. This affects latency for the POS fleet, data-residency obligations, the network path to SUNULIFE, and the Mobile Money providers' willingness to allow-list the environment. Analysed in Document 2, section on data residency.

### 10.5 Availability, backup and continuity

| Target | Value |
|---|---|
| Service availability (back office) | 99.5% monthly, excluding announced maintenance |
| Field availability (collection possible) | ~100% — offline mode makes collection independent of the back office |
| RPO (max data loss) | 15 minutes on the database (binlog shipping); 0 for field collections, which are held on the device until acknowledged |
| RTO (max restoration time) | 4 hours |
| Backups | Full daily + continuous binlog; encrypted; retained 30 days; monthly archive retained 12 months; **restore test every quarter** |

---

## 11. Non-Functional Requirements

| Domain | Requirement |
|---|---|
| **Offline operation** | Full subscription capture and premium collection without network; automatic synchronisation on reconnection; no data loss; no duplicate posting |
| **Performance** | POS screen transition < 1 s; card read → policy list displayed < 2 s online, < 500 ms from cache; back-office list pages < 2 s at 100 000 collections; Excel export of one month generated asynchronously and downloadable |
| **Languages** | French (primary) and English throughout POS and back office |
| **Currency** | XOF, no decimals in display; amounts stored with 2 decimals |
| **Accessibility in the field** | Large touch targets, high-contrast, usable in sunlight, low literacy tolerant (icons + French labels) |
| **Battery** | A full working day of collection on one charge under normal use |
| **Printing** | Every financial operation produces a printed receipt; any receipt can be reprinted within the working day with a clear "DUPLICATA" marking |
| **Auditability** | Every create/update/delete on a sensitive entity recorded with actor, timestamp, before/after values |
| **Maintainability** | Configuration over code: products, caps, fees, receipt templates, field mappings and permissions are configurable by an administrator without a release |

---

## 12. Assumptions, Open Points and Decisions Required

### 12.1 Assumptions

| # | Assumption |
|---|---|
| A1 | The POS terminals are Android devices of the Telpo/Feitian family already supported by the existing application (NFC reader, thermal printer, camera, GPS, 4G) |
| A2 | SUNU provides the SIM cards and the data plan for the 15 terminals |
| A3 | Collectors are SUNU employees or mandated agents, individually identified, and each is assigned a specific terminal |
| A4 | The paper insurance proposal remains the legal document during the pilot; the solution captures and transmits it, it does not replace the signature |
| A5 | The card personalisation station is supplied with a documented interface or accepts a standard file/CSV input for printing and encoding |
| A6 | SUNU provides an extract of existing customers and policies for the Phase 0 import |
| A7 | Amounts are in XOF |

### 12.2 Open points requiring a SUNU decision

| # | Open point | Impact if unresolved | Needed by |
|---|---|---|---|
| **OP-1** | SUNULIFE API existence, documentation and test access (section 8.5) | Determines whether Phase 1 is possible for November or the file-based fallback applies | 15 September 2026 |
| **OP-2** | Provision of the customer/policy extract for the Phase 0 import | Field agents would not see existing customers at the pilot | 10 September 2026 |
| **OP-3** | Card technology: magnetic stripe as written in the TdR, or contactless as recommended | Card procurement lead time; multi-policy capability; clone resistance | 8 September 2026 |
| **OP-4** | Mobile Money route (aggregator or direct) and which entity holds the merchant account | Operator onboarding is the longest external lead time before November | 8 September 2026 |
| **OP-5** | Hosting location and who provides the infrastructure | Environment provisioning blocks UAT | 5 September 2026 |
| **OP-6** | Collector cap amount(s) and whether the cap is uniform or per collector | Configuration only, but needed for UAT scenarios | 15 September 2026 |
| **OP-7** | Definitive list of products for the pilot and their premium/policy-cost rules | Product catalogue configuration | 10 September 2026 |
| **OP-8** | Receipt content and layout, including legal mentions required in Burkina Faso | Receipt template; printer testing | 10 September 2026 |
| **OP-9** | Whether biometric identification of the insured is wanted (capability exists) | Scope and CNIL/CIL declaration in Document 2 | 1 October 2026 |
| **OP-10** | Bank account(s) and cash office(s) designated for collector pay-ins | Pay-in configuration | 15 September 2026 |

---

## 13. Traceability — Requirements to Architecture

| Requirement | Architecture response |
|---|---|
| TdR Need 1 — subscription + first premium | §6.1, components P3–P5, B8, B10, B11 |
| TdR Need 1 — proposal + CNIB photos with agent, product, proposal number, name, GPS | §6.1, P3, P4, B8, table `proposal` |
| TdR Need 1 — card with SUNU branding, insured name, multi-policy | §6.4, §7.1, B13, table `card_policy_link` |
| TdR Need 2 — card read → policy list → collect → receipt | §6.2, P6, P9, P12, B10 |
| TdR Need 2 — collector cap, pay-in, terminal release | §6.3, B12, P14 |
| TdR Need 2 — all payments in back office, Excel export | B17, §8.4 Phase 0 |
| TdR Need 3 — 15 terminals, profiles and permissions, 5 000 cards, personalisation device | B3, B5, B13, §6.4, §10.3 |
| TdR Need 4 — APIs to update the core software | §8, S5–S7 |
| E-mail §1 — SUNULIFE retrieval and synchronisation | §8 in full, S1–S4 |
| E-mail §2 — architecture documentation | This document |
| E-mail §2 — security documentation | Document 2 |
| E-mail §3 — user guide per profile | Document 3 |
| E-mail §4 — physical card | §7.1 |
| E-mail §4 — virtual card via QR code | §7.3 |
| E-mail §4 — Mobile Money | §9 |
| Follow-up request, 16 September 2026 — as-built architecture, import file formats and sample | §14; Document 5 and its sample CSV files |
| E-mail §5 — September deployment, November production | Document 4; §4.3 and §8.4 explain how the dates are protected |

---

## 14. Pilot Implementation — As Built (17 September 2026)

Sections 1 to 13 describe the target architecture. This section records what the pilot environment implements today, so that SUNU IT can assess the running system as well as the design. Capabilities that are not yet built are stated as such.

### 14.1 Pilot deployment

```
 Telpo F20 terminals                         Pilot server (Linux VPS, hosted by DBS)
 ┌────────────────────────┐                 ┌──────────────────────────────────────────┐
 │ SUNU TPE application    │  REST / JSON    │ Apache HTTP Server + PHP 8                │
 │ com.sunu.telpo 1.1.2    │  + multipart    │ Laravel 9 back office                     │
 │ Android, 32-bit ARM     │ ──────────────▶ │   /api/v1/sunu/*     terminal API         │
 │ camera · GPS · NFC ·    │                 │   /business/sunu/*   web back office      │
 │ thermal printer         │                 │ MySQL database                            │
 └────────────────────────┘                 │ File storage: proposal and CNIB images    │
                                             └───────────────────────┬──────────────────┘
 SUNU back-office users ──── web browser ────────────────────────────┘

 SUNULIFE extract ──▶ CSV import (customers + policies)  ──▶ back office
 back office      ──▶ CSV export (premium collections)   ──▶ manual recording in SUNULIFE
```

| Item | Pilot value |
|---|---|
| Server | One Linux VPS hosted by DBS; application, database and image storage on the same host |
| Web and application tier | Apache HTTP Server with PHP 8, Laravel 9 application |
| Database | MySQL, dedicated SUNU schema |
| Image storage | Server file system, one folder per proposal |
| Terminal application | `com.sunu.telpo` version 1.1.2 (build 4), 32-bit ARM, about 15 MB, installed by side-loading |
| Terminals | Telpo F20; each terminal is registered in the back office by its Android device identifier, and requests from unregistered terminals are refused |
| Transport | HTTP on a dedicated port during the pilot; TLS is a pre-production item (§14.6) |

### 14.2 Scope delivered versus planned

| Capability | Target reference | Pilot status |
|---|---|---|
| Insurance product catalogue: Micro Épargne Retraite, Mixte Plus Horizon, Rente Éducation Plus, Micro Quiétude, with DGTCP visa, category, guarantees, premium defaults; editable, activated or deactivated rather than deleted | B7 | Built |
| Subscription on the terminal: product choice, identity, photo of the signed proposal, CNIB front and back, GPS position, offline save when the upload fails | P3–P5, B8 | Built; in field test |
| Back-office proposal review and acceptance, creating the policy `POL-{proposal number}` | B8, B9 | Built |
| Premium collection: policy cost, first premium, recurring premium, each with a unique reference | B10 | Built |
| Policy lookup from the card UID | P6, B13 | Built |
| Collector cap status, pay-in declaration, back-office confirmation or rejection | B12, P14 | Built |
| Collections list and CSV export for recording in SUNULIFE | B17, §8.4 Phase 0 | Built |
| Import of existing customers and policies from a CSV file | §8.4 Phase 0 | Built; file format in Document 5 |
| SUNULIFE API synchronisation | B16, §8 | Not started; depends on OP-1 |
| Mobile Money collection | B15, §9 | Not built; the data model already reserves `momo` as a payment instrument |
| Virtual QR card | B14, P8 | Not built |

### 14.3 Terminal API in the pilot

Base path `/api/v1/sunu`. Every call carries `business_unit_id` and the terminal identifier; the back office resolves the collector from the terminal assignment.

| Method | Path | Purpose |
|---|---|---|
| POST | `/api/v1/agent/login` | Collector login |
| GET | `/products` | Active products, cached on the terminal for offline use |
| POST | `/proposals` | Subscription: identity fields, product, GPS and three images (multipart) |
| GET | `/proposals/{id}` | Proposal detail |
| POST | `/premiums` | Premium collection; `reference` makes a retried upload idempotent |
| GET | `/policies/by-card/{uid}` | Active policies attached to a card |
| GET | `/collector/status` | Cap, uncollected amount, blocked flag |
| POST | `/pay-ins` | Collector pay-in declaration |

### 14.4 Subscription flow as built

```
 Terminal                                     Back office
 1  GET  /products ─────────────────────────▶ active products
 2  collector captures identity, product,
    proposal photo, CNIB front, CNIB back, GPS
 3  POST /proposals (multipart) ────────────▶ validate fields
                                              resolve terminal → collector
                                              store the three images
                                              insert proposal, status "captured"
 4  ◀──────────── JSON: proposal created, or HTTP 422 with the list of field errors
    on network failure: saved offline on the terminal, sent again later

 Back-office user:  Proposals → review images and data → Accept ──▶ policy POL-{proposal number}
```

| Multipart field | Content | Stored as |
|---|---|---|
| `proposal_image` | Photo of the signed proposal form | `proposal_image_path` |
| `id_document_front` | CNIB, front | `id_document_front_path` |
| `id_document_back` | CNIB, back | `id_document_back_path` |

Images are compressed on the terminal to at most 900 KB each. The back office accepts JPEG, PNG and WebP.

### 14.5 Data exchange formats

| Exchange | Direction | Format |
|---|---|---|
| Terminal API | Terminal ↔ back office | JSON, UTF-8; images as multipart/form-data |
| Existing customers and policies | SUNULIFE extract → back office | CSV, UTF-8, comma-separated; specified in Document 5 |
| Premium collections | Back office → SUNU | CSV download |

### 14.6 Lessons from the pilot and pre-production items

Field testing between 14 and 16 September 2026 led to three terminal releases (1.1.0 to 1.1.2):

- **Image uploads.** Each photo now travels under its own field name, is compressed on the terminal, and is sent with a fixed-length request body, because the PHP tier does not read chunked multipart bodies. Server upload limits were raised to match.
- **GPS.** A 0,0 position is treated as missing, and the terminal requests location permission at runtime.
- **Terminal start-up.** NFC and printer libraries load only when needed, and the installation package fell from about 70 MB to about 15 MB.
- **Diagnostics.** Validation errors are returned field by field to the terminal and logged on the server.

Before production, the pilot moves to the target described in section 10 and in Document 2:

1. TLS on all terminal and web traffic (Document 2 §12 and §18).
2. Per-terminal API authentication in addition to the device identifier.
3. Production topology of §10.2: Nginx and PHP-FPM, separate database server, encrypted backups with point-in-time recovery.
4. Hosting location decision (OP-5).
5. SUNULIFE integration phase decision (OP-1).

---

*End of Document 1.*
