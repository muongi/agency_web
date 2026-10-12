# SUNU Assurances Vie Burkina Faso
## Mobile Premium Collection Solution on POS Terminals
### Document 4 — Deployment Plan and Schedule

| Field | Value |
|---|---|
| Document | Deployment Plan & Schedule |
| Version | 1.0 (draft for review) |
| Date | 31 August 2026 |
| Prepared for | SUNU Assurances Vie Burkina Faso |
| Prepared by | DBS SA — Digital Business Services |
| Answers | SUNU requirements e-mail §5 — "initial deployment before the end of September 2026; effective production launch no later than November 2026", and §6 — management meeting of 1 October 2026 |
| Related documents | Doc 1 — Technical Architecture; Doc 2 — Security; Doc 3 — User Guide |

---

## 1. Objectives and Fixed Dates

| # | Milestone | Date | Source |
|---|---|---|---|
| **M0** | Project documentation delivered to SUNU for review | **early September 2026** | This set of four documents |
| **M1** | UAT environment available to SUNU | **14 September 2026** | Derived — prerequisite for M2 |
| **M2** | User acceptance testing completed and signed | **25 September 2026** | Derived — prerequisite for M3 |
| **M3** | **Initial deployment (pilot) live in the field** | **30 September 2026** | **SUNU — hard requirement** |
| **M4** | **Solution presentation to SUNU General Management** | **1 October 2026** | **SUNU — fixed date** |
| **M5** | Pilot operating period, corrections, hardening | 1–31 October 2026 | Derived |
| **M6** | Production go/no-go decision | **28 October 2026** | Derived |
| **M7** | **Effective production launch** | **early November 2026** | **SUNU — hard requirement** |

**M3 and M4 are three and four weeks away.** This plan is built backwards from them and states plainly what must be true for them to hold.

---

## 2. Honest Assessment of the Schedule

SUNU emphasised "the importance of strictly adhering to the schedule for the initial deployment". That deserves a straight answer rather than an optimistic one.

### 2.1 What makes the date achievable

- The solution is a **customisation of a platform already running in production**, not a new build (Doc 1 §2.1, §4). Agents, terminals, roles and permissions, clients, cards with a full lifecycle, transactions with fees and commissions, audit trail, SMS, reporting with Excel export, offline POS operation, receipt printing and NFC card read/write all exist and work today.
- The platform already contains a **voluntary-insurance enrolment and periodic-contribution module** structurally identical to SUNU's proposal → policy → premium model. This is adaptation, not invention.
- The pilot is deliberately **15 terminals in a controlled perimeter**, not a national rollout.

### 2.2 What genuinely threatens the date

| # | Threat | Nature | Owner |
|---|---|---|---|
| **1** | **Hardware procurement**: 15 terminals, 5 000 cards, the personalisation station | Outside software. Lead times are supplier- and customs-driven and cannot be compressed by working harder | SUNU + supplier |
| **2** | **Mobile Money onboarding** with the operators or an aggregator | Commercial and KYC process at a third party; typically several weeks | SUNU |
| **3** | **SUNULIFE API**: existence, documentation, test access | Unknown today (Doc 1, OP-1). Cannot be planned until answered | SUNU / SUNULIFE editor |
| **4** | **Hosting decision and environment provisioning** | Blocks the UAT environment, which blocks everything downstream | SUNU (Doc 1, OP-5) |
| **5** | **SUNU availability for UAT and training** in the last two weeks of September | Acceptance cannot be delegated to the supplier | SUNU |
| **6** | **Card technology decision** (magnetic versus contactless) | Changes what is ordered; ordering the wrong thing costs more than the delay | SUNU (Doc 1, OP-3) |

**Four of the six are on SUNU's side.** That is not a criticism — it is the reality of an integration project, and it is why section 6 lists dated dependencies with named owners.

### 2.3 The scoping decision that protects the date

Rather than risk everything, the pilot scope is deliberately narrowed to the **TdR core**, with the e-mail's additional requests phased across October:

| Delivered for the pilot — 30 September | Delivered during October, live for November |
|---|---|
| Subscription capture with proposal and CNIB photographs, GPS | Mobile Money collection |
| Policy costs and first premium, two receipts | Virtual card (QR) at scale |
| Physical card issuance and multi-policy card | SUNULIFE Phase 1 read-only API sync **(if OP-1 is answered)** |
| Premium collection by card, policy selection, receipt | Refinements from pilot feedback |
| Collector cap, pay-in declaration and confirmation | Remaining hardening items (Doc 2 §12) |
| Back office: profiles, permissions, collections, exports | |
| **Excel export for SUNULIFE** (TdR Need 2) | |
| **Bulk import of existing SUNULIFE customers** (Doc 1 §8.4 Phase 0) | |
| Blocking security items H-1, H-4, H-6 (Doc 2 §12) | |

**Why this split works.** The pilot delivers 100% of the TdR's stated needs. The e-mail's additions — Mobile Money, QR, SUNULIFE API — are real requirements, but none of them is required for a collector to subscribe a client and collect a premium in the field on 30 September. Attempting all of them by that date would put the core at risk for features that depend on third parties we do not control.

**Critically, the Phase 0 bulk import means SUNU's priority requirement — that agents see customers who already exist in SUNULIFE — is met at the pilot even if the API is not available**, using a periodic extract. The intent of the requirement is honoured on time; the mechanism is upgraded later.

If SUNU prefers a different split, this is the conversation to have on 1 October, with the trade-off explicit rather than discovered in the last week of September.

---

## 3. Schedule — September (to the pilot)

### Week 1 · 1–6 September — Foundations and unblocking

| Workstream | Activity | Owner |
|---|---|---|
| Governance | Kick-off; confirm the plan; name contacts on both sides; open-point register live | Both |
| Documents | SUNU reviews Documents 1–4; questions raised in writing | SUNU |
| **Blockers** | **Decisions on OP-3 (card), OP-4 (MoMo), OP-5 (hosting)** | **SUNU** |
| **Blockers** | **SUNULIFE questionnaire sent to the editor (Doc 1 §8.5)** | **SUNU** |
| Procurement | Terminal, card and personalisation-station orders placed | SUNU |
| Infrastructure | Provision UAT and production environments; TLS certificates; DNS | DBS |
| Development | Insurance product and policy model; proposal adapted from the enrolment module | DBS |
| Security | **H-1 (HTTPS end to end, certificate pinning)** and **H-6 (plaintext logging removed)** | DBS |

**Gate G1 — end of week 1:** OP-3, OP-4, OP-5 decided; hardware ordered; environments provisioned. *If OP-5 is not decided this week, M1 slips and M3 is at risk.*

### Week 2 · 7–13 September — Core build

| Workstream | Activity | Owner |
|---|---|---|
| Development | Premium collection engine; receipt templates; collector cap and pay-in; multi-policy card issuance and personalisation export | DBS |
| POS | Subscription stepper adapted; document and CNIB capture; GPS; policy selection screen; cap indicator | DBS |
| Back office | Proposal review queue; collections views; **Excel export for SUNULIFE** | DBS |
| Data | **Phase 0 import**: SUNU provides the customer and policy extract; mapping and test load | SUNU → DBS |
| Security | **H-4 (mobile cryptography)**, **H-2 (session tokens)**, **H-3 (rate limiting)** | DBS |
| Configuration | Products, tariffs, caps, profiles configured from SUNU's answers to OP-6, OP-7, OP-8, OP-10 | Both |
| Documents | Documents updated from SUNU's review comments | DBS |

**Gate G2 — end of week 2:** end-to-end flow demonstrable in the development environment (subscribe → collect → receipt → back office → export). Phase 0 extract received.

### Week 3 · 14–20 September — UAT

| Workstream | Activity | Owner |
|---|---|---|
| **M1** | **UAT environment open to SUNU with synthetic data — 14 September** | DBS |
| Testing | SUNU executes the acceptance scenarios of section 4; defects logged daily | SUNU |
| Correction | Daily defect triage and fix cycle | DBS |
| Hardware | Terminals received, application installed, devices hardened, terminals registered and assigned | Both |
| Cards | Card blanks received; personalisation station installed and tested end to end | Both |
| **Training** | **Collectors (1 day), back office, supervisors, card officer, administrator, IT** — per the Doc 3 annex | DBS + SUNU |
| Security | Authentication and RBAC test, negative tests, vulnerability scan | DBS |

**Gate G3 — 20 September:** no blocking defect open; all users trained; terminals ready in hand.

### Week 4 · 21–27 September — Acceptance and preparation

| Workstream | Activity | Owner |
|---|---|---|
| Testing | Second UAT pass on corrected defects; fraud scenarios of Doc 2 §3.2 replayed | SUNU |
| **M2** | **UAT signed off — 25 September** | **SUNU** |
| Production | Production environment finalised; backup running; **restore test executed**; monitoring and alerts configured and routed | DBS |
| Data | Phase 0 import loaded into production; volumes and spot checks validated by SUNU | Both |
| Operations | Pilot perimeter confirmed: which collectors, which zones, which products; pay-in bank account and cash office configured | SUNU |
| Security | **Go-live security checklist (Doc 2 §18) completed and jointly signed** | Both |
| Documents | Documents 1–4 finalised with UAT screenshots; **French versions delivered** | DBS |

**Gate G4 — 27 September: GO / NO-GO for the pilot.** Criteria in section 5.

### Week 5 · 28–30 September — Pilot launch

| Day | Activity |
|---|---|
| 28 Sept | Production deployment; smoke tests; terminals synchronised in production; final briefing to collectors |
| 29 Sept | **Controlled launch: a small number of collectors, real transactions, close support** |
| 30 Sept | **M3 — full pilot: 15 collectors in the field.** Reinforced support presence; end-of-day review |

### 1 October — M4, management meeting

| Item | Content |
|---|---|
| Live demonstration | Real subscription and real collection performed on a terminal in the room |
| Architecture | Document 1, sections 3, 4, 6 — including the SUNULIFE approach |
| Security | Document 2, sections 4, 8, 12 — including the hardening programme, presented openly |
| Requested functions | Physical card, virtual QR card, Mobile Money — status and October delivery dates |
| SUNULIFE | Section 8 and **the questionnaire of §8.5 — the decisive item to resolve at this meeting** |
| First figures | Pilot collections over the first two days |
| Schedule to November | This document, section 3 |
| Decisions requested | SUNULIFE access, Mobile Money route confirmation, production perimeter |

---

## 4. Schedule — October and November (to production)

### October · weeks 1–4 — Pilot operation and completion

| Week | Focus |
|---|---|
| **1–4 Oct** | Intensive pilot support; daily defect triage; first reconciliation cycle; collect field feedback |
| **5–11 Oct** | **Mobile Money integration** (subject to OP-4 onboarding); virtual QR card at scale; corrections from field feedback |
| **12–18 Oct** | **SUNULIFE Phase 1** read-only sync *if OP-1 resolved*, otherwise the file-based fallback; remaining hardening items H-5, H-7, H-8, H-9 |
| **19–25 Oct** | Full regression testing; load test; **second restore test**; final documentation and French versions verified; training for the extended user population |
| **26–28 Oct** | Pilot assessment; production readiness review; **M6 — go/no-go on 28 October** |

### November — Production launch

| Period | Activity |
|---|---|
| **First days of November** | **M7 — effective production launch.** Progressive extension of the collector population; reinforced support for two weeks |
| Weeks 2–4 | Stabilisation; monitoring; weekly reconciliation; first monthly report to SUNU |
| End of November | Post-launch review; transition to standard support; roadmap for Phase 2 (SUNULIFE write integration, TdR Need 4) |

---

## 5. Acceptance Criteria and Go/No-Go Gates

### 5.1 UAT acceptance scenarios (M2)

Each scenario is executed by SUNU and signed.

| # | Scenario | Pass criterion |
|---|---|---|
| T1 | Full subscription with photographs and GPS | File complete and readable in the back office with all TdR-required fields |
| T2 | Policy costs and first premium | Two distinct receipts printed with correct amounts |
| T3 | Subscription entirely offline, then reconnection | Nothing lost, nothing duplicated |
| T4 | Card issuance for a single-policy insured | Card printed, encoded, active |
| T5 | Card issuance for a **multi-policy** insured | All policies present on the card and displayed on the terminal |
| T6 | Premium collection by card | Correct policy list, correct receipt |
| T7 | Premium collection by QR | Same result as T6 |
| T8 | Collection offline then sync | Collection present once and only once |
| T9 | Cap reached | Terminal blocks new collections; subscription still possible |
| T10 | Pay-in declared and confirmed | Balance reduced, terminal released |
| T11 | Pay-in with a variance | Variance recorded and reported; balance reduced by the real amount |
| T12 | Excel export | File opens, contains every collection of the period, fields usable for SUNULIFE entry |
| T13 | Phase 0 import | An existing SUNULIFE customer is found by a collector in the field |
| T14 | Profiles and permissions | Each profile can do only what Doc 2 §5.2 permits — **negative tests included** |
| T15 | Retry after network timeout | No duplicate collection |
| T16 | Card blocked | Card refused immediately; the client can still pay by search or QR |
| T17 | Audit trail | Every sensitive action traced with actor, timestamp and before/after values |
| T18 | Both languages | Every screen and every receipt correct in French and in English |

### 5.2 Gate G4 — pilot go/no-go (27 September)

**GO requires all of:**

- [ ] UAT signed by SUNU; no blocking or critical defect open
- [ ] Security checklist (Doc 2 §18) signed — including blocking items **H-1, H-4, H-6**
- [ ] 15 terminals in hand, configured, hardened, assigned to named collectors
- [ ] Cards and personalisation station operational; at least 20 cards produced in test
- [ ] Collectors trained and briefings signed
- [ ] Back-office and supervisor staff trained
- [ ] Production environment operational; backup running; **restore tested**
- [ ] Phase 0 data loaded and validated by SUNU
- [ ] Caps, products, tariffs and pay-in accounts configured
- [ ] Support procedure and contacts published on both sides
- [ ] Rollback procedure documented and understood

**If any item fails:** a partial pilot with fewer collectors is preferable to postponing everything — it preserves the 30 September date on real transactions while the missing element is completed. This is the recommended fallback, decided at G4 rather than improvised.

### 5.3 Gate M6 — production go/no-go (28 October)

- [ ] Pilot operated at least 3 weeks with no critical incident
- [ ] All pilot defects closed or accepted with a workaround
- [ ] All hardening items (Doc 2 §12) closed
- [ ] Mobile Money in production **or** formally deferred by SUNU with a date
- [ ] SUNULIFE Phase 1 in production **or** the file-based fallback accepted and operating
- [ ] Reconciliation clean for 3 consecutive weeks
- [ ] Documentation final, in French, delivered
- [ ] Extended user population trained
- [ ] Restore test passed a second time
- [ ] Support model agreed for production

---

## 6. Dependencies on SUNU — Dated

Each line is a prerequisite for the schedule. A missed date moves the plan; that is stated here so it is never a surprise later.

| # | Dependency | Needed by | Blocks | Owner |
|---|---|---|---|---|
| **D1** | Hosting decision and environment provisioning (OP-5) | **5 Sept** | M1, therefore everything | SUNU IT |
| **D2** | Card technology decision (OP-3) | **8 Sept** | Card ordering; M3 | SUNU Operations |
| **D3** | Mobile Money route decision and start of onboarding (OP-4) | **8 Sept** | MoMo delivery in October; M7 | SUNU Finance |
| **D4** | Hardware order placed: 15 terminals, 5 000 cards, personalisation station | **8 Sept** | M3 — the longest lead time in the project | SUNU Procurement |
| **D5** | Customer and policy extract for the Phase 0 import (OP-2) | **10 Sept** | T13; the SUNULIFE priority requirement at the pilot | SUNU IT |
| **D6** | Pilot product list, tariffs and policy-cost rules (OP-7) | **10 Sept** | Configuration; UAT | SUNU Operations |
| **D7** | Receipt content and required legal mentions (OP-8) | **10 Sept** | Receipt templates; UAT | SUNU Operations + Legal |
| **D8** | Answers to the SUNULIFE questionnaire (OP-1) | **15 Sept**, or firmly at M4 | SUNULIFE Phase 1 in October | SUNU IT + editor |
| **D9** | Collector cap amounts (OP-6) | **15 Sept** | Configuration; UAT | SUNU Operations |
| **D10** | Pay-in bank account and cash office designated (OP-10) | **15 Sept** | Pay-in configuration; T10 | SUNU Finance |
| **D11** | Named testers available for UAT, 14–25 Sept | **14 Sept** | M2 | SUNU |
| **D12** | Collectors available for training, week of 14 Sept | **14 Sept** | M3 | SUNU HR + Operations |
| **D13** | Pilot perimeter defined: collectors, zones, products | **21 Sept** | M3 | SUNU Operations |
| **D14** | UAT sign-off | **25 Sept** | M3 | SUNU |
| **D15** | CIL declaration filed (Doc 2 §6.5) | Before M7 | Production launch | SUNU (data controller) |
| **D16** | Data-processing agreement signed | Before M7 | Production launch | Both |

---

## 7. Risk Register

Probability × Impact, with the mitigation actually in place — not a generic one.

| # | Risk | P | I | Mitigation | Owner |
|---|---|---|---|---|---|
| **R1** | **Hardware not delivered in time for 30 September** | **High** | **High** | Order in week 1 (D4); confirm supplier lead times in writing; **fallback: pilot on a smaller number of terminals**, even 5, to hold the date on real transactions | SUNU |
| **R2** | **SUNULIFE API unavailable or undocumented** | **High** | Medium | Architecture already designed with Phase 0 (extract import) and Phase 1/2 phasing (Doc 1 §8.4); the file-based fallback is fully supported; **the date is not dependent on this** | Both |
| **R3** | **Mobile Money onboarding longer than expected** | **High** | Medium | Start immediately (D3); aggregator recommended as the faster route; MoMo is outside the pilot scope, so it threatens M7 and not M3 | SUNU |
| **R4** | UAT reveals major defects | Medium | High | Two-week UAT with a daily correction cycle; core built on proven code; scope deliberately narrowed | DBS |
| **R5** | SUNU staff unavailable for UAT or training in September | Medium | **High** | Named participants secured in week 1 (D11, D12); training material ready in advance | SUNU |
| **R6** | Poor mobile coverage in the pilot zones | Medium | Medium | Offline mode is a core design property, not an option; test explicitly in a low-coverage zone during UAT | Both |
| **R7** | Field resistance or misuse by collectors | Medium | Medium | One full day of training with role-play; pocket card; reinforced support in the first week; supervisor monitoring | SUNU |
| **R8** | Magnetic cards chosen, then found unable to hold the multi-policy payload | Medium | High | Decision documented in Doc 1 §7.2 with contactless recommended; decide in week 1 (D2) before ordering | SUNU |
| **R9** | Fraud during the pilot | Low | High | Caps bound the exposure; SMS to the insured; GPS; append-only ledger; fraud indicators monitored weekly from day one (Doc 2 §8) | Both |
| **R10** | Hosting decision late | Medium | **High** | D1 dated at 5 September; **fallback: temporary hosting provided by DBS for UAT** so the schedule is not blocked while SUNU finalises production hosting | Both |
| **R11** | Scope expansion during September | **High** | **High** | Scope frozen at G1; any addition is assessed and scheduled for October or later — **not absorbed silently into the pilot** | Both |
| **R12** | Personalisation station arrives with an undocumented interface | Medium | Medium | Confirm the interface with the supplier at ordering (D4); the fallback is a standard CSV/file export, which the architecture already supports | SUNU |

**The three that decide the September date: R1, R5, R11.** Two are on SUNU's side and one is shared. All three are manageable — if they are managed in week 1.

---

## 8. Responsibilities (RACI)

R = Responsible · A = Accountable · C = Consulted · I = Informed

| Activity | SUNU Ops | SUNU IT | SUNU Finance | DBS |
|---|---|---|---|---|
| Requirements validation | **A/R** | C | C | C |
| Technical architecture | C | **A** | I | **R** |
| Security design | I | **A** | I | **R** |
| Development and configuration | C | I | I | **A/R** |
| Hardware procurement | **A/R** | C | C | C |
| Mobile Money onboarding | C | C | **A/R** | C |
| SUNULIFE access | C | **A/R** | I | C |
| Environment provisioning | I | **A** | I | **R** |
| Data import (Phase 0) | C | **A/R** | I | **R** |
| UAT execution | **A/R** | R | R | C |
| Training delivery | **A** | I | I | **R** |
| Pilot go/no-go decision | **A** | C | C | C |
| Production deployment | C | **A** | I | **R** |
| Pay-in confirmation (operational) | C | I | **A/R** | — |
| Fraud monitoring | **A/R** | C | R | C |
| CIL declaration | C | **A/R** | I | C |
| Production support | C | **A** | I | **R** |

---

## 9. Deliverables

| # | Deliverable | Format | Date |
|---|---|---|---|
| L1 | Document 1 — Technical Architecture | EN then FR | 31 Aug (EN) / 25 Sept (FR) |
| L2 | Document 2 — Security | EN then FR | 31 Aug (EN) / 25 Sept (FR) |
| L3 | Document 3 — User Guide (all profiles) | EN then FR, with screenshots after UAT | 31 Aug (EN) / 25 Sept (final) |
| L4 | Document 4 — Deployment Plan | EN then FR | 31 Aug (EN) / 25 Sept (FR) |
| L5 | UAT environment | Running system | 14 Sept |
| L6 | Customised POS application (APK) | Signed binary | 14 Sept (UAT) / 28 Sept (prod) |
| L7 | Customised back-office platform | Running system | 14 Sept (UAT) / 28 Sept (prod) |
| L8 | UAT test book and results | Document | 25 Sept |
| L9 | Signed security checklist | Document | 27 Sept |
| L10 | Training material and pocket cards | Document + print | 14 Sept |
| L11 | Operating procedures (backup, incident, support) | Document | 27 Sept |
| L12 | Pilot assessment report | Document | 26 Oct |
| L13 | Production handover file | Document | Nov |

---

## 10. Support Model

| Phase | Coverage | Response target |
|---|---|---|
| **Pilot, first week (29 Sept – 5 Oct)** | Reinforced, with presence available in the field | Critical: immediate · High: 2 h |
| **Pilot, remainder of October** | Business hours, dedicated channel | Critical: 2 h · High: 4 h · Medium: 1 working day |
| **Production launch, first two weeks** | Reinforced | Critical: 1 h · High: 4 h |
| **Standard production** | Business hours + on-call for critical | Critical: 2 h · High: 1 working day · Medium: 3 working days |

| Severity | Definition |
|---|---|
| **Critical** | Field collection impossible, or data loss |
| **High** | A major function unavailable with no workaround |
| **Medium** | Function degraded, workaround exists |
| **Low** | Cosmetic or improvement request |

**Escalation:** level 1 SUNU internal support (trained administrator) → level 2 DBS technical → level 3 project management on both sides. Contacts published and posted before M3.

---

## 11. Rollback

| Situation | Action |
|---|---|
| A release introduces a blocking defect | Roll back to the previous version, documented per release; collections already recorded are unaffected — the ledger is append-only |
| The pilot cannot operate at all | Collectors revert to receipt books for the affected zone; collections captured on the terminals are recovered and reconciled; the pilot is relaunched after correction |
| A terminal fails | Reassign the collector to a spare; unsynchronised data recovered from the original device |
| Data corruption | Restore from backup (RPO 15 min, RTO 4 h); collections held on terminals are re-synchronised, idempotency preventing duplication |

Field data is never lost by a rollback: collections stay on the terminal until the server acknowledges them, and the unique-reference constraint makes re-sending safe.

---

## 12. Post-Launch Roadmap

| Phase | Content | Indicative period |
|---|---|---|
| **Phase 2** | SUNULIFE write integration — automatic posting of collections (TdR Need 4, S5–S7) | Q4 2026 / Q1 2027 |
| **Phase 3** | Extension of the terminal fleet beyond 15; additional zones | Per SUNU's commercial plan |
| **Phase 4** | Insured-facing mobile application: virtual card, payment history, self-service premium payment | 2027 |
| **Phase 5** | Additional products; claims declaration in the field; collector incentive schemes | 2027 |

---

## 13. Summary — Schedule at a Glance

```
SEPTEMBER 2026
 W1  1–6   ▓▓▓ decisions · procurement · environments · H-1 H-6
           ▲ G1: OP-3, OP-4, OP-5 decided · hardware ordered
 W2  7–13  ▓▓▓ core build · Excel export · Phase 0 import · H-2 H-3 H-4
           ▲ G2: end-to-end flow demonstrable
 W3 14–20  ▓▓▓ UAT · terminals received · TRAINING
           ▲ M1 (14th): UAT open     ▲ G3 (20th): no blocking defect
 W4 21–27  ▓▓▓ acceptance · production prep · restore test · security checklist
           ▲ M2 (25th): UAT signed   ▲ G4 (27th): PILOT GO/NO-GO
 W5 28–30  ▓▓▓ deployment · controlled launch
           ▲ M3 (30th): ★ PILOT LIVE — SUNU HARD REQUIREMENT

OCTOBER 2026
  1        ▲ M4: ★ PRESENTATION TO GENERAL MANAGEMENT
  1–4      pilot support · first reconciliation
  5–11     Mobile Money · QR at scale
 12–18     SUNULIFE Phase 1 (or fallback) · H-5 H-7 H-8 H-9
 19–25     regression · load test · restore test · final FR documentation
 26–28     pilot assessment    ▲ M6 (28th): PRODUCTION GO/NO-GO

NOVEMBER 2026
  early    ▲ M7: ★ PRODUCTION LAUNCH — SUNU HARD REQUIREMENT
  weeks 2–4  stabilisation · monitoring · first monthly report
```

---

*End of Document 4.*
