# SUNU Assurances Vie Burkina Faso
## Mobile Premium Collection Solution on POS Terminals
### Document 1 — Solution Architecture

| Field | Value |
|---|---|
| Document | Solution Architecture |
| Version | 2.0 |
| Date | 17 September 2026 |
| Prepared for | SUNU Assurances Vie Burkina Faso, Central Operations Department and IT |
| Prepared by | DBS SA — Digital Business Services |
| Related documents | Doc 2 Security; Doc 3 User Guide; Doc 4 Deployment Plan; Doc 5 Data Import and Exchange Formats |

---

## 1. Business Context

SUNU sells micro-insurance to people who rarely come to a branch. Its agents meet them in markets, villages and workplaces, sign them up on paper and collect premiums in cash. Today those collections reach SUNULIFE, the core insurance system, late and by hand.

The solution puts a payment terminal in each agent's hands and connects it to a web back office. The agent registers new customers and collects premiums on the terminal, prints a receipt on the spot, and the back office sees the operation as soon as it is sent.

| Need | What it means in practice |
|---|---|
| Subscription in the field | The agent picks the product, enters the customer's details, photographs the signed proposal and both sides of the CNIB, and records the GPS position. The first premium and the policy cost are collected with a receipt for each |
| Premium collection by card | The agent reads the customer's card, sees the customer's active policies, picks one, collects the premium and prints a receipt |
| Collector cap and pay-in | Each agent can hold only a set amount of uncollected cash. At the limit the terminal blocks further collections until the agent pays the money into a SUNU bank account or cash office and the back office confirms it |
| Back office | All payments visible in one place and exportable for SUNULIFE; user profiles and permissions; 15 terminals; 5 000 cards and a personalisation station |
| SUNULIFE link | Existing customers and policies loaded from SUNULIFE, and collections sent back to it |
| Other payment and card options | Mobile Money (Orange Money, Moov Money) and a virtual card shown as a QR code |

---

## 2. Architecture Overview (Level 1)

<!-- figure: 01-system-context | Figure 1 — System context. Solid lines are needed for field collection. Dashed lines are optional channels: if one is down, only that channel stops and cash collection carries on. -->

There are two parts to the system, the terminals and the back office, and everything else connects to the back office.

**The insured** deals only with the agent. They sign the proposal, pay, and present their card.

**The collection agent** is assigned to one terminal. The terminal knows who is using it, so every subscription and payment carries the agent's name, the terminal and the time.

**The POS terminal** is where the work happens. It captures subscriptions, collects premiums, reads cards, prints receipts and takes photos. When the network drops, the agent can keep an operation on the terminal and send it later.

**The back-office platform** is the reference for everything collected: customers, policies, payments, receipts, agent caps and pay-ins. SUNU staff use it from a web browser, with access set by profile.

**External systems** sit behind the back office, never behind the terminal. SUNULIFE stays the reference for the insurance contract; the back office is the reference for collection. Mobile Money, the SMS gateway and the card personalisation station are each connected separately, so a problem with one of them does not stop an agent from collecting cash.

---

## 3. Component Architecture (Level 2)

The tables below list the building blocks and whether each one is already live or still planned.

### 3.1 POS terminal

| Component | What it does | Pilot |
|---|---|---|
| Login and session | Agent signs in with their own account; session closes after inactivity | Live |
| Product list | Downloads the active products and keeps a copy for offline use | Live |
| Subscription | Step-by-step form: product, identity, beneficiary, then three photos and GPS | Live |
| Photo capture | Proposal form, CNIB front, CNIB back; each photo compressed on the terminal | Live |
| Premium collection | Policy cost, first premium or recurring premium, each with a unique reference | Live |
| Card reading | Reads the NFC card and asks the back office for the customer's active policies | Live |
| Receipt printing | Prints a receipt on the built-in thermal printer for every payment | Live |
| Collector cap | Shows the agent's uncollected amount and blocks collection at the limit | Live |
| Pay-in | Agent declares a bank deposit or cash office payment | Live |
| Offline save | Keeps an operation on the terminal when it cannot be sent | Live |
| QR virtual card | Scans and checks a customer's QR card | Planned |
| Mobile Money | Starts a payment on the customer's phone and waits for confirmation | Planned |

### 3.2 Back office

| Component | What it does | Pilot |
|---|---|---|
| Users, profiles, permissions | Web login; five back-office profiles (back-office agent, supervisor, card officer, administrator, IT) plus the field agent profile | Live |
| Agents and terminals | Agent register, terminal register, which agent uses which terminal | Live |
| Product catalogue | SUNU products with DGTCP visa, category, guarantees and default premiums; products are switched off, never deleted | Live |
| Proposals | List of subscriptions sent from the field with their photos and GPS; review and acceptance | Live |
| Policies | Policies created from accepted proposals or imported from SUNULIFE | Live |
| Premium collections | Every payment with agent, terminal, reference and receipt; CSV export for SUNULIFE | Live |
| Caps and pay-ins | Cap per agent, running uncollected amount, pay-in confirmation or rejection | Live |
| Customer import | Loads existing customers and policies from a CSV file (Document 5) | Live |
| Cards | Card register and status (active, blocked, replaced…), link between a card and several policies | Live |
| Audit trail | Records who changed users, agents, terminals, customers and cards, and when | Live |
| SUNULIFE connector | Automatic exchange of customers, policies and collections with SUNULIFE | Planned, waiting for SUNULIFE API access |
| Mobile Money connector | Orange Money and Moov Money, directly or through an aggregator | Planned |
| QR card issuing | Signed QR credential for a customer | Planned |

---

## 4. Technology Stack

| Layer | Technology |
|---|---|
| Terminal hardware | Telpo F20 Android POS: touch screen, NFC reader, thermal printer, camera, GPS, 4G |
| Terminal application | Android, written in Java; minimum Android API level 21 |
| Terminal local storage | SQLite |
| Back-office application | PHP 8 with the Laravel 9 framework; server-rendered web pages |
| Database | MySQL |
| Web server | Apache HTTP Server in the pilot; Nginx with PHP-FPM planned for production |
| File storage | Server file system for proposal and CNIB photos |
| API | REST with JSON; photos sent as multipart/form-data |
| Exports and imports | CSV, UTF-8 |
| Languages | French and English in both the terminal and the back office |
| Hosting | Linux virtual server |

---

## 5. POS Application

The terminal application is the agent's whole working tool. After login the home screen offers four things: subscribe a customer, collect a premium, declare a pay-in, and view the day's activity.

**Subscribing a customer.** The agent chooses a product from the list downloaded at login. The form then walks through the customer's identity, other details and beneficiary. Next come three photos, taken with the terminal's camera: the signed proposal, and the front and back of the CNIB. Each photo is reduced to 900 KB or less before sending, which keeps uploads short on a weak mobile connection. The terminal adds its GPS position and sends everything in a single request. If the back office refuses the request, the agent sees the reason field by field. If there is no network, the agent can save the subscription and send it later.

**Collecting a premium.** The agent reads the customer's card. The terminal asks the back office for the policies linked to that card and shows them. The agent picks the policy, confirms the amount, and the terminal sends the payment with a reference it generated itself. If the same payment is sent twice, for example after a timeout, the back office recognises the reference and does not record it again. A receipt is printed for each payment.

**Staying within the cap.** Every collection is added to the agent's uncollected amount. The terminal shows how close the agent is to the limit. Once it is reached, the back office refuses further collections until the agent declares a pay-in and a supervisor confirms it.

**Device identity.** Each terminal is registered in the back office under its Android device identifier. The terminal sends this identifier with every request, and the back office uses it to find the agent. A terminal that is not registered is refused.

**Installation.** The application is about 15 MB and is installed directly on the terminal. The current pilot version is 1.1.2.

---

## 6. Back-Office Application

The back office is a web application used by SUNU staff and the DBS support team. It also serves the API used by the terminals, so both share one database and one set of rules.

**Main screens.** Under the Premium collection menu: Proposals, Policies, Premium collections, Pay-ins, Products and Customer import. Around these are the standard administration screens for users, profiles, agents, terminals and cards.

**From proposal to policy.** A subscription sent from a terminal arrives as a proposal with its three photos and GPS position. A back-office user checks the photos against the data and accepts it. Acceptance creates the policy, numbered `POL-` followed by the proposal number.

**Payments.** Every payment is stored once, with its amount, type (policy cost, first premium, recurring premium), payment method, agent, terminal, reference, receipt number and time. The collections screen can export them as a CSV file for recording in SUNULIFE until the direct connection is in place.

**Caps and pay-ins.** Each agent has a cap and a running balance of cash not yet paid in. A pay-in declared on the terminal appears in the back office, where a supervisor confirms the amount received or rejects it. Confirmation reduces the balance and unblocks the terminal.

**Existing customers.** SUNU's current customers and policies are loaded from a CSV extract of SUNULIFE. Document 5 describes the file.

**Main data.**

```
business unit (SUNU Vie Burkina Faso)
 ├── users ── profiles ── permissions
 ├── agents ── terminals
 │      └── cap and uncollected balance ── pay-ins
 ├── products
 ├── customers
 │      ├── proposals (with photos and GPS)
 │      ├── policies
 │      └── cards ── card-to-policy links
 ├── premium collections ── financial transactions
 └── audit trail
```

**Traceability.** Changes to users, profiles, agents, terminals, customers and cards are written to an audit trail with the user, the time and the values before and after. Proposals, policies and payments carry their own agent, terminal and timestamps.

---

## 7. API

The terminals talk to the back office through a REST API. Requests and responses are JSON in UTF-8. Subscriptions with photos are sent as `multipart/form-data`.

### 7.1 Endpoints

| Method | Path | Used for |
|---|---|---|
| POST | `/api/v1/agent/login` | Agent login |
| GET | `/api/v1/sunu/products` | Active products |
| POST | `/api/v1/sunu/proposals` | New subscription with photos |
| GET | `/api/v1/sunu/proposals/{id}` | One proposal |
| POST | `/api/v1/sunu/premiums` | Record a premium payment |
| GET | `/api/v1/sunu/policies/by-card/{uid}` | Active policies for a card |
| GET | `/api/v1/sunu/collector/status` | Agent's cap and uncollected amount |
| POST | `/api/v1/sunu/pay-ins` | Declare a pay-in |

Every call includes `business_unit_id`. Calls made on behalf of an agent also include `device_serial`, the terminal's identifier.

### 7.2 New subscription

`POST /api/v1/sunu/proposals`

| Field | Required | Notes |
|---|---|---|
| `business_unit_id` | Yes | SUNU's business unit |
| `device_serial` | Yes | Terminal identifier (`agent_id` is accepted instead) |
| `insurance_product_id` | Yes | From the product list |
| `last_name`, `first_names` | Yes | Customer name |
| `phone` | No | |
| `proposal_number` | No | Number printed on the paper form; generated if missing |
| `latitude`, `longitude` | No | Decimal degrees |
| `client_reference` | No | Unique ID set by the terminal; a resend with the same value returns the first result |
| `form_data` | No | Remaining form answers as JSON |
| `proposal_image` | No | Photo of the signed proposal; JPEG, PNG or WebP |
| `id_document_front`, `id_document_back` | No | CNIB photos; JPEG, PNG or WebP |

A successful call returns HTTP 201 and the new proposal.

### 7.3 Premium payment

`POST /api/v1/sunu/premiums`

| Field | Required | Notes |
|---|---|---|
| `business_unit_id` | Yes | |
| `device_serial` | Yes | |
| `policy_id` or `proposal_id` | One of them | Proposal for a first premium, policy otherwise |
| `amount` | Yes | XOF |
| `collection_kind` | Yes | `policy_cost`, `first_premium` or `recurring_premium` |
| `reference` | Recommended | Unique ID set by the terminal; stops double recording |
| `payment_instrument` | No | `cash`, `momo` or `bank`; `cash` in the pilot |
| `reference_period`, `payment_frequency` | No | For example `2026-09`, `monthly` |
| `receipt_number`, `latitude`, `longitude` | No | |

The response contains the payment and the agent's updated cap status, so the terminal can warn the agent straight away.

Example response to `GET /api/v1/sunu/collector/status` (figures are illustrative):

```
{
  "success": true,
  "data": {
    "agent_id": 12,
    "cap_amount": 500000,
    "warning_threshold_pct": 80,
    "currency": "XOF",
    "uncollected_amount": 415000,
    "utilisation_pct": 83,
    "is_blocked": false,
    "blocked_at": null,
    "last_pay_in_at": "2026-09-15T16:40:12+00:00"
  }
}
```

### 7.4 Errors

| HTTP code | Meaning |
|---|---|
| 201 | Created |
| 404 | Business unit, proposal or card not found |
| 422 | Invalid data. The body has a `message` and an `errors` object listing each field and the problem with it; also returned for an unregistered terminal |
| 423 | Agent blocked: a pay-in is needed before collecting again |
| 500 | Server error; the operation was not recorded and can be sent again |

### 7.5 Conventions

| Item | Format |
|---|---|
| Amounts | Number in XOF |
| Dates | `YYYY-MM-DD`; date-times in ISO 8601 |
| GPS | Decimal degrees |
| Photos | JPEG, PNG or WebP; the terminal sends 900 KB or less per photo |

In the pilot the terminal is identified by its registered device identifier. Encrypted transport (HTTPS) and a per-terminal access token will be in place before production; Document 2 covers this.

---

*End of Document 1.*
