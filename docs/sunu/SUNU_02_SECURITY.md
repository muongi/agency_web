# SUNU Assurances Vie Burkina Faso
## Mobile Premium Collection Solution on POS Terminals
### Document 2 — Security Architecture, Procedures and Controls

| Field | Value |
|---|---|
| Document | Security Architecture & Procedures |
| Version | 1.0 (draft for review) |
| Date | 31 August 2026 |
| Prepared for | SUNU Assurances Vie Burkina Faso — Central Operations Department |
| Prepared by | DBS SA — Digital Business Services |
| Answers | SUNU requirements e-mail §2 — "documentation describing the procedures and mechanisms implemented to ensure the security of the solution and data" |
| Related documents | Doc 1 — Technical Architecture; Doc 3 — User Guide; Doc 4 — Deployment Plan |
| Classification | Confidential — SUNU internal and delivery team |

---

## 1. Purpose, Scope and Approach

### 1.1 Purpose

This document describes the security mechanisms and procedures protecting the Mobile Premium Collection Solution and the data it handles: who can do what, how money and identity data are protected, how the solution behaves under attack or failure, and what SUNU must do operationally to keep it secure.

### 1.2 Scope

| In scope | Out of scope |
|---|---|
| POS application and terminal fleet | SUNU's internal corporate network and workstations |
| Back-office web application and REST API | SUNULIFE's own internal security (SUNU/editor responsibility) |
| Database, file storage, backups | Mobile Money operators' internal security |
| Integrations: SUNULIFE, Mobile Money, SMS | Physical security of SUNU premises |
| Card issuance chain (physical and virtual) | Legal validity of the paper proposal |
| Operational security procedures for SUNU staff | |

### 1.3 Approach and honesty statement

The solution is a customisation of a platform already running in production for financial services. That is an advantage — the controls described here are mostly *operating* controls, not promises.

It also means the platform carries pre-existing implementation choices that are **not appropriate for an insurer's production deployment** and are being corrected as part of this project. Section 12 lists them explicitly, with the fix and the target date, rather than omitting them. An insurer's IT review will find them; declaring them first is the only defensible position and it is how this project treats security throughout.

### 1.4 Reference frameworks

The design is aligned to, without claiming certification against:

- **ISO/IEC 27001:2022** — information security management, used as the control taxonomy in section 15.
- **ISO/IEC 27002** — implementation guidance.
- **PCI-DSS v4.0** — used as a *design reference* for card and payment handling. Note: the solution stores **no card payment credentials and no bank card data**; the cards issued are insurance identity cards. Full PCI-DSS scope therefore does not apply, but its controls on key management, logging and network segmentation are adopted as good practice.
- **OWASP ASVS 4.0 Level 2** and **OWASP Top 10 (2021)** — application security verification.
- **OWASP MASVS** — mobile application security.
- **Loi n°001-2021/AN portant protection des personnes à l'égard du traitement des données à caractère personnel (Burkina Faso)** and the supervision of the **Commission de l'Informatique et des Libertés (CIL)** — personal data.
- **CIMA Code** and **BCEAO** requirements applicable to insurance operations and to electronic money acceptance in the UEMOA zone.

---

## 2. Assets and Security Objectives

### 2.1 Assets to protect

| # | Asset | Confidentiality | Integrity | Availability |
|---|---|---|---|---|
| A1 | Premiums collected in cash held by collectors | — | **Critical** | **Critical** |
| A2 | Collection and transaction records | High | **Critical** | High |
| A3 | Insured personal data (identity, CNIB, phone, address) | **Critical** | High | Medium |
| A4 | Identity document images (proposal, CNIB) | **Critical** | High | Medium |
| A5 | Policy data mirrored from SUNULIFE | High | **Critical** | Medium |
| A6 | Card stock and card personalisation data | High | High | Medium |
| A7 | Virtual card signing keys | **Critical** | **Critical** | High |
| A8 | Collector and back-office user credentials | **Critical** | **Critical** | High |
| A9 | SUNULIFE and Mobile Money integration credentials | **Critical** | **Critical** | High |
| A10 | Biometric templates (if the option is enabled) | **Critical** | **Critical** | Low |
| A11 | POS terminals themselves | Medium | High | High |
| A12 | Audit trail | High | **Critical** | Medium |

### 2.2 Security objectives

1. **No premium collected can be lost, altered or double-counted.**
2. **No premium can be diverted without leaving a trace that identifies who, when and where.**
3. **Insured personal data is disclosed to no one outside the authorised SUNU perimeter.**
4. **A stolen terminal or a stolen card yields no money and no exploitable personal data.**
5. **Every sensitive action is attributable to a named individual.**
6. **A security failure degrades the service; it does not destroy the collection record.**

---

## 3. Threat Model

### 3.1 Threat actors

| Actor | Motivation | Capability | Priority |
|---|---|---|---|
| **T1 — Dishonest collector** | Divert collected premiums, issue false receipts, under-declare | Legitimate credentials, physical possession of the terminal, field autonomy | **Highest** |
| **T2 — Thief** | Resell a stolen terminal or card, use it fraudulently | Physical access only | High |
| **T3 — Insured acting fraudulently** | Obtain a receipt without paying, claim a payment not made, reuse a QR | Interaction with the collector | High |
| **T4 — External attacker** | Steal the personal-data base, extort, disrupt | Internet access, standard tooling | High |
| **T5 — Dishonest internal back-office user** | Confirm a false pay-in, alter a collection, exfiltrate data | Privileged application access | High |
| **T6 — Interceptor on the mobile network** | Capture traffic between the terminal and the platform | Network position, public Wi-Fi | Medium |
| **T7 — Compromised third party** (aggregator, SMS provider, hosting) | Access data in transit or at rest | Legitimate integration access | Medium |

### 3.2 Principal threat scenarios and countermeasures

| # | Scenario | Countermeasures |
|---|---|---|
| **TH-1** | Collector collects a premium and pockets it, printing no receipt | Receipt is generated by the system, not by the collector; sequential receipt numbering per terminal with gap detection; SMS confirmation to the insured (§8.4); insured education that a payment without a printed receipt is invalid; collections reconciled against the collector's cap and pay-ins |
| **TH-2** | Collector prints a receipt then cancels or deletes the record | Collections are **append-only**; no delete on the device or in the back office; a correction is a new reversal entry, permission-gated and audited; the terminal's local queue is not user-accessible |
| **TH-3** | Collector accumulates cash and disappears | Per-collector cap (Doc 1 §6.3) bounds maximum exposure; terminal blocks new collections at the cap; daily monitoring of uncollected balances; ageing alert on collectors above threshold for more than N days |
| **TH-4** | Collector declares a pay-in larger than actually deposited | Pay-in is a *declaration*; only back-office confirmation against the bank statement or cash-office count reduces the balance; variance recorded; two-person confirmation above a configurable amount |
| **TH-5** | Terminal stolen | Device locked by agent credentials and inactivity timeout; no cash on the device; local data encrypted and scoped to that agent; remote deactivation of the agent account and of the terminal from the back office; unsynchronised collections recoverable on return |
| **TH-6** | Card stolen or cloned | The card holds an identifier, **never money and never a balance**; a card identifies, it does not authorise payment — the collector's authenticated session does; card blocked instantly in the back office; recommendation of contactless with per-card keys over clonable magnetic stripe (Doc 1 §7.2) |
| **TH-7** | Forged or replayed QR virtual card | Cryptographic signature verified offline on the terminal; credential expiry; revocation list synchronised to terminals; the QR identifies but does not authorise; presenting a QR still produces a receipt in the true insured's name, so replay yields no financial gain |
| **TH-8** | Interception of POS ↔ platform traffic | Mandatory TLS 1.2+; certificate pinning in the POS application; no sensitive data in URLs; rejection of plaintext HTTP |
| **TH-9** | Theft of the personal-data base | Encryption at rest; database not exposed to the Internet; least-privilege database accounts; encrypted backups; access logging; alerting on abnormal export volumes |
| **TH-10** | Compromise of an API key | Keys are per business unit, hashed at rest, revocable instantly, rate-limited, usage-logged with last-use timestamp; rotation procedure (§5.4) |
| **TH-11** | Back-office user exports the whole client base | Export is permission-gated; every export is logged with actor, filters and row count; volumetric alerting; exports watermarked with the requesting user |
| **TH-12** | Injection / XSS / CSRF on the back office | Framework-level parameterised queries, output escaping, CSRF tokens on all state-changing web requests, strict server-side validation on every endpoint |
| **TH-13** | Brute force on collector or user credentials | Rate limiting and progressive lockout, credential-stuffing protection, no username enumeration in error messages, alert on repeated failures |
| **TH-14** | Malicious or negligent SUNULIFE data injection corrupting policies | Strict schema validation on ingestion; a sync never deletes a local collection; anomalies quarantined in an exception queue rather than applied |

---

## 4. Defence in Depth — Layered Overview

```
 ┌───────────────────────────────────────────────────────────────────┐
 │ L7 GOVERNANCE   security policy · roles · training · CIL          │
 │                 declaration · supplier review · incident plan     │
 ├───────────────────────────────────────────────────────────────────┤
 │ L6 MONITORING   audit trail · API logs · alerting · fraud         │
 │                 indicators · quarterly review                     │
 ├───────────────────────────────────────────────────────────────────┤
 │ L5 DATA         encryption at rest · classification · minimisation│
 │                 retention · encrypted backups · restore testing   │
 ├───────────────────────────────────────────────────────────────────┤
 │ L4 APPLICATION  RBAC · input validation · idempotency ·           │
 │                 append-only ledger · maker-checker · caps         │
 ├───────────────────────────────────────────────────────────────────┤
 │ L3 IDENTITY     user auth · MFA for admins · agent auth ·         │
 │                 API keys · terminal binding · session control     │
 ├───────────────────────────────────────────────────────────────────┤
 │ L2 NETWORK      TLS 1.2+ · cert pinning · WAF · rate limiting ·   │
 │                 allow-listing · no public database                │
 ├───────────────────────────────────────────────────────────────────┤
 │ L1 PHYSICAL     terminal custody · card stock control ·           │
 │                 personalisation station · datacentre              │
 └───────────────────────────────────────────────────────────────────┘
```

No single control is relied upon. The cap (L4) bounds the loss that a compromised collector (L1/L3) can cause; the audit trail (L6) makes it detectable; the pay-in confirmation (L4) makes it recoverable.

---

## 5. Identity and Access Management

### 5.1 Back-office users

| Control | Implementation |
|---|---|
| Authentication | Username/e-mail + password over HTTPS |
| Password storage | bcrypt with per-password salt; never reversible, never logged |
| Password policy | Minimum 12 characters, three character classes, blocked against a common-password list, no reuse of the last 5, expiry configurable (default 90 days for privileged profiles) |
| First login | Forced password change |
| Failed attempts | Progressive delay then temporary lock; alert to the administrator |
| **Multi-factor authentication** | **Required for Administrator and IT profiles**; TOTP application or SMS code. See §12 item H-5 |
| Session | Idle timeout (default 15 min for privileged profiles, 30 min otherwise); absolute lifetime; session fixed on login; secure, HttpOnly, SameSite cookies |
| Deactivation | Immediate on departure; accounts are disabled, never deleted, to preserve the audit trail |
| Review | Quarterly review of accounts and permissions by SUNU (§14.2) |

### 5.2 Role-based access control

The platform implements granular permissions grouped into roles, assignable globally or per business unit. Profiles defined for SUNU:

| Profile | Purpose | Notable permissions | Explicitly denied |
|---|---|---|---|
| **Collection Agent (POS)** | Field collection | Subscribe, collect premium, declare pay-in, print/reprint own receipts, view own collections | Any back-office access; viewing other agents' data; confirming a pay-in; modifying a collection |
| **Back-Office Agent** | Daily processing | Review proposals, view all collections, generate and download exports, manage insured records | Confirming pay-ins; user management; changing caps or fees |
| **Supervisor / Controller** | Financial control | Confirm pay-ins, view caps and balances, handle exception queues, run reconciliation | User management; changing system configuration |
| **Card Issuance Officer** | Card chain | Allocate cards, generate personalisation files, mark cards as written, block/replace cards | Financial operations; user management |
| **Administrator (SUNU)** | Configuration | Manage users, roles, agents, terminals, products, caps, fees | Direct database access; deleting audit records |
| **IT / Super Admin** | Technical | Environment configuration, integrations, credentials, technical monitoring | *Configured with no permission to confirm pay-ins* — technical power must not equal financial power |

**Principle applied throughout: separation of the person who collects, the person who confirms and the person who configures.** No profile holds all three.

### 5.3 Collector authentication on the POS

| Control | Implementation |
|---|---|
| Credential | Individual username + password/PIN, verified server-side against the bcrypt hash |
| First use | Forced PIN change on first login |
| Session token | Short-lived signed token issued at login, renewed on activity, invalid after the inactivity timeout. See §12 item H-2 |
| Offline authentication | Offline unlock permitted for a bounded period using a locally stored verifier; beyond that period the terminal requires an online re-authentication before further collection |
| Terminal binding | The agent is assigned to a specific terminal in the back office; a login from an unassigned terminal is refused and alerted. Terminal identity = registered serial number |
| Revocation | Disabling the agent in the back office denies the next authentication and refuses that agent's synchronisations |
| Inactivity | Automatic lock; re-entry of the PIN required |

### 5.4 Machine-to-machine credentials (API keys)

| Control | Implementation |
|---|---|
| Scope | One key per business unit, transmitted in a dedicated header |
| Storage | Hashed at rest; the plaintext value is displayed once at creation and never retrievable afterwards |
| Lifecycle | Activation flag allowing instant revocation; last-use timestamp recorded; usage logged per request |
| Rotation | Documented procedure: create the new key → configure the terminals → verify traffic on the new key → revoke the old. Rotation at least annually and immediately on suspicion of compromise |
| Rate limiting | Per key and per IP. See §12 item H-3 |

### 5.5 Third-party integration credentials

SUNULIFE, Mobile Money and SMS credentials are held per business unit in a dedicated credential store, separate from the integration definitions, encrypted at rest with the application key, never written to logs, and never displayed after entry. Only the IT profile may modify them, and every modification is audited.

---

## 6. Data Protection

### 6.1 Classification

| Class | Examples | Handling |
|---|---|---|
| **C4 — Critical** | Passwords, PINs, signing keys, integration credentials, biometric templates | Encrypted or hashed; never logged; never exported; no display |
| **C3 — Confidential** | CNIB numbers, identity images, insured personal data, policy data | Encrypted at rest and in transit; permission-gated access; access logged; masked in listings |
| **C2 — Internal** | Collections, transactions, caps, reports | Access by profile; audited; exportable by authorised profiles only |
| **C1 — Public** | Product names, general documentation | No restriction |

### 6.2 Encryption in transit

- **HTTPS/TLS 1.2 minimum, TLS 1.3 preferred**, on every channel: POS ↔ platform, browser ↔ platform, platform ↔ SUNULIFE, platform ↔ Mobile Money.
- Modern cipher suites only; SSLv3, TLS 1.0 and 1.1 disabled; HSTS enabled.
- **Certificate pinning in the POS application**, defeating interception by a rogue proxy on a hostile mobile network.
- Plaintext HTTP refused, not redirected, on API endpoints. See §12 item H-1.

### 6.3 Encryption at rest

| Data | Protection |
|---|---|
| Passwords and PINs | bcrypt hash, never encrypted (irreversible by design) |
| Integration credentials | Symmetric encryption with the application key, held outside the code repository |
| Identity images (proposal, CNIB) | Encrypted storage volume; non-guessable file names; served only through an authenticated, permission-checked, time-limited link — never a public URL |
| Database | Encryption at rest at the volume level; column-level encryption for the most sensitive fields |
| Backups | Encrypted before leaving the server; key held separately from the backups |
| POS local database | Application-private storage, encrypted; cleared on logout of the assigned agent; no export path exposed to the device user |

### 6.4 Data minimisation

- The POS holds only what the assigned collector needs: his/her own clients and collections, and the policy cache required for offline work. **No terminal ever holds the full customer base.**
- Identity images are uploaded and then removed from the device once acknowledged by the server.
- Exports contain only the columns required by the report's purpose.
- Listings mask sensitive identifiers (e.g. CNIB shown partially); full display is a distinct, logged action.

### 6.5 Personal data and Burkina Faso law

| Obligation | Response |
|---|---|
| Declaration of processing to the CIL | **SUNU is the data controller** and files the declaration; DBS acts as processor and provides the technical description required |
| Lawful basis | Performance of the insurance contract and pre-contractual steps |
| Information of the data subject | Notice on the proposal form and a summary mention on the receipt |
| Rights of access, rectification, erasure | Back-office procedure documented in Document 3; requests tracked and answered within the legal period |
| Retention limits | Enforced per Document 1 §5.4; automated archival and purge jobs |
| Processor obligations | Data-processing agreement between SUNU and DBS covering confidentiality, sub-processors, breach notification, return/destruction on termination |
| Cross-border transfer | Depends on the hosting decision (Doc 1, OP-5). If hosting is outside Burkina Faso, the transfer must be covered in the CIL declaration and by contractual clauses |

### 6.6 Biometric data (optional capability)

The platform supports fingerprint capture and verification. **For SUNU this is an option, not a default.** If SUNU activates it:

- Only a **mathematical template** is stored, never a fingerprint image; the template is not reversible into an image.
- The template is C4 data: encrypted, never exported, never displayed, never transmitted to third parties including SUNULIFE.
- Explicit, informed, written consent from the insured is required, with a non-biometric alternative always available.
- It requires a specific CIL declaration by SUNU, biometric data being a sensitive category.
- Recommendation: **do not enable biometrics for the pilot.** It adds legal and consent complexity for no gain over card + QR at this stage. Revisit once the solution is in production.

---

## 7. Card and Virtual Card Security

### 7.1 Core security property

**A SUNU card is an identity token, not a payment instrument.** It carries no balance, stores no money, holds no bank credential, and cannot by itself authorise any debit. Presenting a card causes the terminal to *look up* an insured and display their policies. Money only moves when an authenticated collector records a collection. This single design choice removes the entire class of "stolen card = stolen money" risk.

### 7.2 Physical card controls

| Control | Implementation |
|---|---|
| Content written | Card identifier, insured reference, policy slots. **No CNIB, no address, no amounts, no PIN** |
| Printed content | SUNU branding, insured's last and first name, card number, expiry — as required by the TdR |
| Clone resistance | Contactless cards with per-card keys strongly recommended over magnetic stripe, which is trivially cloneable (Doc 1 §7.2, open point OP-3) |
| Lifecycle | Full status model with blocking reason, timestamps, and replacement chain; a replaced card is permanently unusable |
| Blocking | Immediate in the back office; propagated to terminals on the next sync and enforced server-side at once |
| Stock control | Blank cards are inventoried; each card number is traceable from stock to insured; periodic stock count reconciled against issuance records |
| Personalisation station | Physically secured, access-controlled, in a SUNU-controlled room; personalisation files are generated per batch, transferred over an internal channel, and deleted after use |

### 7.3 Virtual card (QR) controls

| Control | Implementation |
|---|---|
| Integrity | Cryptographically signed payload (HMAC-SHA256 or ECDSA P-256); any alteration invalidates the signature |
| Offline verification | The terminal verifies the signature without network access; a forged QR is rejected on the device |
| Key management | Versioned signing key (`kid` in the payload); private/HMAC key held server-side in the credential store; the terminal holds only what it needs to verify; annual rotation, with overlapping validity so cards in circulation keep working |
| Expiry | Every credential carries an issue and expiry date; expired QRs are refused |
| Revocation | Revocation list synchronised to terminals at each sync; server-side rejection is immediate |
| Anti-replay | A QR identifies but never authorises. Even a perfectly copied QR only produces a collection recorded **in the true insured's name against the true insured's policy** — there is no fraudulent gain |
| Distribution | Printed on the policy schedule or sent by SMS link; never sent with any secret |
| Privacy | The payload contains a reference and a display name — no CNIB, no address, no amount |

---

## 8. Financial and Anti-Fraud Controls

This section addresses the highest-priority threat: the dishonest collector (T1).

### 8.1 Append-only collection ledger

Collections and transactions are **never physically deleted or overwritten**. A correction is a new, opposite entry, requiring an explicit permission, a mandatory reason, and generating an audit record. Every entry carries a unique reference and a link to its terminal, agent, timestamp and, where available, GPS position.

### 8.2 Cap and pay-in as a loss ceiling

The collector cap (Doc 1 §6.3) is the primary financial control: it **bounds the maximum amount any single collector can be holding at any moment**. The terminal enforces it — at the cap, new collections are refused until a pay-in is confirmed. This converts an unbounded fraud exposure into a known, configurable, per-collector limit.

### 8.3 Segregation of duties on pay-in

```
   collects  ─────►  COLLECTOR        (may declare, may not confirm)
   confirms  ─────►  SUPERVISOR       (may confirm, may not collect)
   configures ────►  ADMINISTRATOR    (may set the cap, may do neither)
   above threshold ► SECOND APPROVER  (maker-checker, configurable amount)
```

Confirmation requires evidence: bank reference or deposit-slip image for a bank deposit, cash-office counting record for a cash hand-over. Any variance between declared and confirmed amounts is recorded, attributed and reported — it is never silently absorbed.

### 8.4 Detection controls

| Control | Purpose |
|---|---|
| Sequential receipt numbering per terminal, with automatic gap detection | Detects suppressed or destroyed receipts |
| SMS confirmation of the collection to the insured's phone | The insured becomes an independent witness to every collection — the strongest single control against TH-1 |
| GPS position on each collection | Detects collections recorded far from any plausible client location, or clustered at the collector's home |
| Timing analysis | Flags improbable sequences (many collections in seconds, activity at implausible hours) |
| Ageing of uncollected balances | Flags collectors who hold cash longer than the policy allows |
| Reconciliation: collections − confirmed pay-ins = uncollected balance | Continuous three-way check |
| Mobile Money settlement reconciliation | No MoMo collection is final until it appears in the provider's settlement file |
| Duplicate-reference rejection at database level | Makes double-posting structurally impossible, even under retry |
| Volumetric alerting on exports and on sensitive lookups | Detects data exfiltration by an internal user |

### 8.5 Receipt integrity

The receipt is produced by the application, not typed by the collector. It carries the receipt number, product, policy or proposal number, amount, date and time, collector reference, and terminal identity. A reprint is explicitly marked **DUPLICATA** and is itself logged. Insured-facing communication should state plainly: *a payment without a printed SUNU receipt is not a payment.*

---

## 9. Terminal and Mobile Application Security

| Control | Implementation |
|---|---|
| Fleet registry | Every terminal registered with serial number, SIM, alias and model; assignment to a named agent with dated history |
| Agent–terminal binding | Login refused from a terminal not assigned to that agent; attempt alerted |
| Application distribution | Signed APK distributed through a controlled channel; installation from unknown sources disabled on the fleet; no public store distribution |
| Device hardening | Recommended: kiosk/COSU mode restricting the terminal to the SUNU application; developer options and USB debugging disabled; screen lock enforced |
| Local storage | Application-private, encrypted, agent-scoped; wiped on agent logout |
| Anti-tamper | Root/tamper detection at startup; a rooted device is refused with an alert raised to the back office |
| Secrets on device | No shared static secret in the application package. Per-device keys provisioned at enrolment and held in the Android keystore where the hardware supports it. See §12 item H-4 |
| Remote response to loss | Agent account disabled → next authentication and next synchronisation refused; the terminal is marked lost in the registry; unsynchronised collections are recovered if the device is returned |
| Screenshots | Disabled on screens displaying personal data |
| Logs | No PIN, password, token, full CNIB or personal data written to device logs |

---

## 10. Integration Security

### 10.1 SUNULIFE

| Control | Implementation |
|---|---|
| Transport | TLS 1.2+ mandatory. If SUNULIFE is on SUNU premises, a site-to-site VPN or private link is preferred over the public Internet |
| Authentication | As required by SUNULIFE: OAuth 2.0, JWT bearer, HTTP basic or mutual TLS — the adapter framework already supports these strategies |
| Least privilege | The integration account is granted only the operations of Doc 1 §8.2 — no administrative rights in SUNULIFE |
| Network restriction | SUNU to allow-list the platform's egress IP addresses |
| Input validation | Every field received from SUNULIFE is schema-validated before it touches the database; malformed or anomalous payloads are quarantined, not applied |
| Non-destructive by construction | A synchronisation from SUNULIFE can never delete or alter a local collection record |
| Traceability | Full request/response logging with sensitive fields masked; every exchange replayable and idempotent |
| Availability isolation | Circuit breaker; SUNULIFE downtime never blocks field collection |

### 10.2 Mobile Money

| Control | Implementation |
|---|---|
| Credentials | Held in the encrypted credential store; distinct credentials per environment; never in the code repository |
| Callback authentication | Provider callbacks verified by signature and by source-IP allow-list; an unverified callback is rejected and alerted |
| Idempotency | Every initiation carries an idempotency key; a repeated callback cannot create a second collection |
| Amount validation | The amount confirmed by the provider is compared with the amount requested; any mismatch blocks the collection and raises an exception |
| No PIN handling | The insured enters their Mobile Money PIN **on their own handset, on the operator's channel**. The PIN never transits the POS, the application or the platform |
| Settlement reconciliation | Daily three-way reconciliation (platform / provider report / bank credit) with an ageing exception queue |
| Timeout handling | An unconfirmed payment stays pending and is resolved by reconciliation; **the receipt is printed only after confirmation** |

### 10.3 SMS

Provider credentials in the credential store; message content limited to the minimum (amount, policy reference, date) with **no CNIB, no full policy details, no link carrying a secret**; delivery status logged.

---

## 11. Logging, Audit and Monitoring

### 11.1 What is recorded

| Log | Content | Retention |
|---|---|---|
| **Audit trail** | Every create/update/delete on a sensitive entity: actor, action, subject, before/after values, timestamp, IP, grouped by operation batch | 5 years |
| **Authentication log** | Successes and failures, back office and POS, with IP, terminal and user agent | 12 months |
| **API log** | Dedicated middleware already records transaction, card and OTP API traffic: endpoint, payload (sensitive fields masked), response, status, latency | 12 months |
| **Financial log** | Every collection, pay-in, confirmation, correction and cap change | 10 years |
| **Integration log** | Every SUNULIFE and Mobile Money exchange with status and attempt count | 24 months |
| **Technical log** | Errors, exceptions, performance | 90 days |

### 11.2 Log protection

Logs are append-only to application users; no application profile can delete an audit record. Sensitive values (passwords, PINs, tokens, full CNIB, biometric templates) are masked at the point of writing — a logging configuration that would emit them in plaintext must not exist in production. See §12 item H-6.

### 11.3 Alerts

| Event | Severity | Recipient |
|---|---|---|
| Repeated authentication failures on one account | Medium | Administrator |
| Login from an unassigned terminal | High | Supervisor + Administrator |
| Rooted or tampered device detected | High | IT |
| Receipt number gap detected | High | Supervisor |
| Collector above cap for more than N days | Medium | Supervisor |
| Pay-in variance beyond tolerance | High | Supervisor + Finance |
| Abnormal export volume | High | Administrator + IT |
| Integration failure rate above threshold | Medium | IT |
| Unverified Mobile Money callback | High | IT |
| Backup failure | High | IT |

### 11.4 Review cadence

Daily: alert queue and exception queues. Weekly: cap and pay-in reconciliation. Monthly: access review of privileged accounts, integration health. Quarterly: full user and permission review, backup restore test, security patch review.

---

## 12. Hardening Programme — Pre-Existing Items to Correct

These items exist in the current platform and are **corrected as part of this project**, before the production launch. They are listed openly so SUNU can verify each one at acceptance. Each carries a target aligned with Document 4.

| # | Item | Current state | Correction | Target |
|---|---|---|---|---|
| **H-1** | **Transport security** | The POS application's default API base URL is plaintext `http://`, and API endpoints accept HTTP | Enforce HTTPS end to end; refuse plaintext on the API; add certificate pinning in the POS application; HSTS on the back office | **Before UAT** — mandatory gate |
| **H-2** | **Collector session token** | The POS login returns the agent's profile but issues no bounded session token; subsequent calls rely on a shared API key | Issue a short-lived, per-agent signed token, renewed on activity, revocable server-side and invalidated on inactivity | Before pilot |
| **H-3** | **API rate limiting** | API routes are not rate-limited | Throttling per API key, per agent and per IP, with progressive lockout and alerting | Before pilot |
| **H-4** | **Mobile-side cryptography** | A PIN-handling utility in the POS application uses a hardcoded symmetric key compiled into the application, in ECB mode. A key present in the APK is extractable, and ECB leaks structure | Remove the hardcoded key; per-device keys provisioned at enrolment and stored in the Android keystore; AES-GCM (authenticated encryption) replacing ECB; PIN verification performed server-side against the bcrypt hash rather than by reversible encryption on the device | Before pilot |
| **H-5** | **MFA for privileged profiles** | Back-office authentication is single-factor | TOTP or SMS second factor, mandatory for Administrator and IT profiles | Before production |
| **H-6** | **Plaintext logging switch** | A configuration flag can cause one-time codes to be logged in plaintext | Flag permanently disabled in production and removed from the production configuration; verified by the go-live checklist | Before UAT |
| **H-7** | **API key lookup efficiency** | Key verification iterates over all active keys performing a hash comparison on each — correct, but linear in the number of keys and CPU-costly | Indexed lookup on a key prefix, then a single constant-time comparison | Before production |
| **H-8** | **Security headers** | Not fully configured | Content-Security-Policy, X-Frame-Options, X-Content-Type-Options, Referrer-Policy, Permissions-Policy | Before production |
| **H-9** | **Secrets management** | Secrets held in environment configuration on the server | Restricted file permissions, excluded from the repository, documented rotation procedure; a managed secret store where the hosting choice permits | Before production |
| **H-10** | **Dependency and patch management** | Ad hoc | Monthly dependency vulnerability scan; documented patch window; critical fixes within 7 days | Continuous from go-live |

**Acceptance position:** H-1, H-4 and H-6 are treated as **blocking** — the pilot does not go live without them. The others are scheduled before the November production launch and verified by the checklist in section 16.

---

## 13. Business Continuity and Resilience

| Scenario | Impact | Response | Target |
|---|---|---|---|
| Mobile network unavailable in the field | None on collection | Offline mode; local queue; synchronisation on reconnection | Continuous operation |
| Back-office platform unavailable | Collection continues, reporting stops | Redundancy, monitoring, documented restart procedure | RTO 4 h |
| Database corruption or loss | Potential data loss | Encrypted daily full backup + continuous binlog; quarterly restore test | RPO 15 min |
| Terminal lost, stolen or broken | One collector stops | Spare terminals in stock; agent reassigned to a spare; unsynchronised data recovered if the device returns | Same day |
| SUNULIFE unavailable | Freshness of policy data degrades | Local mirror + queued push with retry; Excel fallback of TdR Need 2 | No collection impact |
| Mobile Money provider unavailable | MoMo channel stops | Cash collection continues; pending payments resolved by reconciliation | No collection impact |
| Data-centre loss | Total outage | Off-site encrypted backups; documented rebuild procedure; contractual restoration time | RTO 24 h |
| Ransomware | Encryption of the environment | Off-site immutable backups; least privilege; restore procedure tested quarterly | RTO 24 h |

---

## 14. Security Procedures for SUNU

Technology is half the control. These are SUNU's operational responsibilities.

### 14.1 Collector onboarding and offboarding

**Onboarding:** identity verification and background check per SUNU HR policy → account created by the Administrator with the Collection Agent profile only → terminal assigned and recorded → PIN issued out-of-band and changed on first use → cap set → security briefing signed (custody of the terminal, no PIN sharing, receipt discipline, immediate loss reporting).

**Offboarding, executed the same day:** terminal returned and its assignment closed → all cash paid in and confirmed → account disabled (never deleted) → residual balance reconciled to zero → cards in the collector's possession returned and reconciled against stock.

### 14.2 Access review

Quarterly, the Administrator reviews every account and its permissions with the relevant department head; unused accounts are disabled; the review is documented and retained.

### 14.3 Card stock

Blank cards are held in a locked store under a named custodian. Movements in and out are recorded. A monthly count reconciles physical stock against the back-office issuance record; any discrepancy is investigated as a security incident.

### 14.4 Credential handling

PINs and passwords are issued through a channel separate from the username, changed on first use, never shared, never written down, never transmitted by e-mail or messaging. No SUNU or DBS staff member will ever ask a collector or an insured for their PIN.

### 14.5 Awareness

Security briefing at onboarding, an annual refresher, and a short quarterly reminder on the specific risks that matter here: phishing on back-office accounts, receipt discipline in the field, terminal custody, and reporting anything unusual immediately.

---

## 15. Control Mapping (ISO/IEC 27001:2022 Annex A)

| Control area | Reference | Where addressed |
|---|---|---|
| Policies for information security | A.5.1 | §14, security policy annexed at go-live |
| Segregation of duties | A.5.3 | §5.2, §8.3 |
| Contact with authorities | A.5.5 | §17 (CIL, CIMA) |
| Threat intelligence | A.5.7 | §12 H-10 |
| Information security in supplier relationships | A.5.19–5.22 | §10, processor agreement §6.5 |
| Incident management | A.5.24–5.28 | §16 |
| Business continuity | A.5.29–5.30 | §13 |
| Legal and contractual requirements | A.5.31–5.34 | §1.4, §6.5 |
| Screening and terms of employment | A.6.1–6.2 | §14.1 |
| Awareness and training | A.6.3 | §14.5 |
| Disciplinary process | A.6.4 | SUNU HR policy |
| Physical security | A.7.1–7.4 | §7.2, §14.3, hosting |
| Equipment and secure disposal | A.7.9–7.14 | §9, §14.1 |
| User endpoint devices | A.8.1 | §9 |
| Privileged access rights | A.8.2 | §5.1, §5.2 |
| Information access restriction | A.8.3 | §5.2, §6.4 |
| Authentication | A.8.5 | §5.1, §5.3 |
| Capacity management | A.8.6 | Doc 1 §10.3 |
| Malware protection | A.8.7 | §9, host hardening |
| Vulnerability management | A.8.8 | §12 H-10, §16.3 |
| Configuration management | A.8.9 | §12 H-9 |
| Data masking | A.8.11 | §6.4, §11.2 |
| Data leakage prevention | A.8.12 | §8.4, §11.3 |
| Backup | A.8.13 | §13 |
| Logging and monitoring | A.8.15–8.16 | §11 |
| Network security and segregation | A.8.20–8.22 | Doc 1 §10.2, §10 |
| Secure development | A.8.25–8.29 | §16.3 |
| Change management | A.8.32 | §16.3 |
| Test information | A.8.33 | §16.3 — anonymised data in UAT |

---

## 16. Assurance

### 16.1 Incident response

| Step | Action | Timing |
|---|---|---|
| 1. Detect | Alert, user report, or monitoring | Continuous |
| 2. Qualify | Severity assessed against the scale below | ≤ 1 h |
| 3. Contain | Disable the account, revoke the key, isolate the terminal, block the card | ≤ 4 h (critical) |
| 4. Notify | SUNU incident contact; CIL if personal data is breached; insured persons if legally required | Per legal deadline |
| 5. Eradicate and restore | Fix, restore from backup if needed, verify integrity | Per severity |
| 6. Review | Written post-incident report, root cause, corrective actions with owners and dates | ≤ 10 working days |

| Severity | Definition | Examples |
|---|---|---|
| **Critical** | Personal-data breach, systemic fraud, total outage | Database exfiltration, compromise of an administrator account |
| **High** | Localised fraud, loss of a terminal with data, integration compromise | Dishonest collector, stolen terminal |
| **Medium** | Attempt without impact, isolated malfunction | Brute-force attempt blocked |
| **Low** | Minor anomaly | Isolated configuration error |

### 16.2 Testing before go-live

| Test | Scope | When |
|---|---|---|
| Authentication and RBAC test | Every profile against every restricted action, including negative tests | Before UAT |
| Application vulnerability scan | Back office and API — OWASP Top 10 | Before UAT and before production |
| POS application review | MASVS-aligned: storage, cryptography, transport, anti-tamper | Before pilot |
| Fraud scenario testing | Each scenario of §3.2 replayed in UAT | During UAT |
| Restore test | Full restoration from backup into a clean environment | Before production |
| Load test | Peak collection volume | Before production |
| Independent penetration test | Recommended, commissioned by SUNU | Recommended before production |

### 16.3 Secure development and change management

Version-controlled source; peer review before merge; no secret in the repository; separate environments with no real data in UAT; releases versioned, changelogged and approved by SUNU before production; documented rollback for every release; monthly dependency vulnerability scan; critical patches within 7 days.

---

## 17. Responsibility Matrix

| Domain | SUNU | DBS |
|---|---|---|
| Data controller status, CIL declaration | **Owner** | Provides the technical description |
| Application and infrastructure security | Validates | **Owner** |
| Hosting security (if SUNU-hosted) | **Owner** | Advises |
| User account creation and review | **Owner** | Provides tooling and procedure |
| Collector vetting and onboarding | **Owner** | Provides the security briefing content |
| Physical custody of terminals and card stock | **Owner** | Provides registry and procedures |
| Cap definition and pay-in confirmation | **Owner** | Provides the mechanism |
| Monitoring and alert handling | Handles business alerts | Handles technical alerts |
| Incident response | Decides, notifies authorities | Investigates, contains, remediates |
| Backups and restore testing | Validates results | **Owner** (executes) |
| Security patching | Approves windows | **Owner** |
| SUNULIFE-side security | **Owner** | Secures the integration endpoint on our side |

---

## 18. Security Acceptance Checklist (go-live gate)

To be signed jointly before the production launch.

**Blocking — no production launch without these**

- [ ] HTTPS/TLS 1.2+ enforced on every channel; plaintext HTTP refused (H-1)
- [ ] Certificate pinning active in the POS application (H-1)
- [ ] Hardcoded mobile cryptographic key removed; per-device keys and authenticated encryption in place (H-4)
- [ ] Plaintext one-time-code logging disabled and removed from the production configuration (H-6)
- [ ] Bounded, revocable collector session tokens in place (H-2)
- [ ] API rate limiting active (H-3)
- [ ] MFA enforced for Administrator and IT profiles (H-5)
- [ ] Every profile tested against every restricted action, including negative tests
- [ ] Encrypted backup running and a full restore successfully tested
- [ ] Audit trail verified complete on collection, pay-in and card operations
- [ ] Duplicate-reference rejection verified at database level
- [ ] No secret present in the source repository or in the APK
- [ ] CIL declaration filed by SUNU
- [ ] Data-processing agreement signed

**Required before production**

- [ ] Security headers configured (H-8)
- [ ] API key lookup hardened (H-7)
- [ ] Secrets management procedure applied and rotation documented (H-9)
- [ ] Vulnerability scan run with no unresolved critical or high finding
- [ ] Alerts configured and routed to named recipients
- [ ] Terminals hardened: kiosk mode, unknown sources disabled, screen lock
- [ ] Collector security briefings signed
- [ ] Incident response contacts published on both sides
- [ ] Access review procedure scheduled and owner named

---

*End of Document 2.*
