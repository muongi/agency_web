# SUNU Assurances Vie Burkina Faso
## Mobile Premium Collection Solution on POS Terminals
### Document 3 — User Guide (all profiles)

| Field | Value |
|---|---|
| Document | User Guide |
| Version | 1.0 (draft for review) |
| Date | 31 August 2026 |
| Prepared for | SUNU Assurances Vie Burkina Faso |
| Prepared by | DBS SA — Digital Business Services |
| Answers | SUNU requirements e-mail §3 — "a user guide for the different profiles who will be using the platform" |
| Related documents | Doc 1 — Technical Architecture; Doc 2 — Security; Doc 4 — Deployment Plan |
| Note | Screenshots are added at the end of UAT, once screens are frozen. Placeholders are marked 〔SCREEN〕. |

---

## How to use this guide

Each profile has a self-contained part. **Nobody needs to read the whole document.** Print only the part that concerns you.

| If you are… | Read | Pages of interest |
|---|---|---|
| A collection agent in the field | **Part A** | Everything, plus the pocket card in §A.12 |
| A back-office agent | **Part B** | Everything |
| A supervisor / controller | **Part C** | Everything, plus §B.3 |
| A card issuance officer | **Part D** | Everything |
| An administrator | **Part E** | Everything |
| IT | **Part F** | Everything, plus Documents 1 and 2 |

Common to all: §0 (first steps), Part G (troubleshooting), Part H (glossary).

---

## 0. First Steps — Everyone

### 0.1 Your account

Your account is created by the SUNU administrator. You receive:

- a **username**;
- a **temporary password or PIN**, sent separately from the username.

On first login you are required to change it. Choose something you can remember without writing it down.

> **Security rules that apply to every profile.** Never share your password or PIN. Never let someone else work under your account — every action is recorded under your name and you are accountable for it. No one from SUNU or DBS will ever ask you for your PIN. If you think someone knows it, change it immediately and tell your supervisor.

### 0.2 Choosing your language

The whole solution works in **French and English**, on the terminal and in the back office. Change it at any time from the language selector; your choice is remembered for next time.

### 0.3 If you are locked out

After several wrong attempts your account locks temporarily. Wait, then try again. If you have genuinely forgotten your password, contact the administrator — they reset it and you set a new one on next login. Nobody can read your existing password, not even IT.

---

# PART A — COLLECTION AGENT (POS Terminal)

*You are the person in the field. You subscribe new clients and collect premiums. This part is your complete manual.*

## A.1 Your terminal

Your terminal is assigned to you personally and recorded against your name. You cannot work on someone else's terminal, and they cannot work on yours.

| What it has | What you use it for |
|---|---|
| Touch screen | Everything |
| Camera | Photographing the proposal and the client's CNIB |
| Card reader | Reading the client's SUNU card |
| Printer | Receipts |
| GPS | Recording where the subscription or collection took place |
| SIM / mobile data | Sending data to the back office |

**Before leaving in the morning:** terminal charged, paper roll loaded plus a spare, log in successfully once while you still have good network.

## A.2 Logging in

〔SCREEN: login〕

1. Turn on the terminal, open the SUNU application.
2. Enter your **username**.
3. Enter your **PIN**.
4. Press **Log in**.

The home screen shows: your name, your terminal, **your current uncollected balance**, and your remaining margin before your cap.

**The terminal locks itself** after a period of inactivity. Re-enter your PIN to continue — nothing is lost.

**Working without network.** You can log in and work offline for a bounded period after your last online login. Beyond that, the terminal asks you to reconnect once. Plan to be in coverage at least once a day.

## A.3 Home screen and what the colours mean

〔SCREEN: home〕

```
  ┌────────────────────────────────────┐
  │  OUEDRAOGO Ali · TPE-BF-007        │
  │                                    │
  │  Uncollected:  185 000 FCFA        │  ← what you are holding
  │  Cap:          250 000 FCFA        │  ← your ceiling
  │  ████████████████░░░░  74%         │
  │                                    │
  │  ┌──────────┐   ┌──────────┐      │
  │  │ NEW      │   │ COLLECT  │      │
  │  │ SUBSCR.  │   │ PREMIUM  │      │
  │  └──────────┘   └──────────┘      │
  │  ┌──────────┐   ┌──────────┐      │
  │  │ PAY IN   │   │ MY DAY   │      │
  │  └──────────┘   └──────────┘      │
  │                                    │
  │  ⟳ 3 items waiting to sync         │
  └────────────────────────────────────┘
```

| Colour of the bar | Meaning | What you should do |
|---|---|---|
| **Green** | Well below your cap | Carry on |
| **Orange** | Approaching your cap | Plan your pay-in today |
| **Red** | Cap reached — **collection blocked** | Pay in now (§A.8). You can still take new subscriptions |

The **⟳ counter** shows items not yet sent to the back office. It clears itself when you have network. It is normal to see it in the field.

## A.4 New subscription (with first premium)

*Use this when a prospect signs a new policy.*

**Before touching the terminal:** fill in the paper proposal, have the client sign it, and have their CNIB to hand. The paper document remains the legal contract — the terminal captures and transmits it.

〔SCREEN: subscription stepper〕

**Step 1 — Product.** Select the product (e.g. Micro-Quiétude, Micro Épargne Retraite).

**Step 2 — Proposal number.** Type the number printed on the paper proposal. Type it carefully — it is how the back office and SUNULIFE will match this subscription.

**Step 3 — Client identity.** Last name, first name(s), date of birth, phone number, CNIB number. If the client says they are already a SUNU customer, use **Search existing client** first — if they are found, their details fill in automatically and no duplicate is created.

**Step 4 — Photographs.** Three photographs:
1. the **signed proposal**;
2. the **CNIB, front**;
3. the **CNIB, back**.

Hold the terminal steady, fill the frame with the document, avoid shadow and glare. After each shot the terminal shows you the result: press **Retake** if any text is unreadable. *A blurred photo means the back office cannot process the file and someone will call you about it.*

**Step 5 — Location.** The terminal captures GPS automatically. Wait for the green indicator. If GPS cannot fix — inside a building, for example — step outside briefly; if it still fails, continue: the subscription is not blocked.

**Step 6 — Policy costs.** Enter the amount received for the policy costs → **Collect** → **the terminal prints receipt no. 1**. Give it to the client.

**Step 7 — First premium.** Enter the amount of the first premium → **Collect** → **the terminal prints receipt no. 2**. Give it to the client.

> **These are two separate receipts and both must be handed over.** One for the policy costs, one for the first premium. This is a formal SUNU requirement.

**Step 8 — Confirmation.** A summary appears. If you have network the file is sent immediately; if not it joins the queue and departs on its own later. **Either way the client is properly registered and holds valid receipts.**

**What happens next.** The back office reviews the file, validates it, and orders the client's card. The client is told how they will receive it.

## A.5 Collecting a premium from an existing client

*This is your most frequent operation.*

〔SCREEN: collect premium〕

**Step 1 — Identify the client.** Three ways:

| Method | How | When |
|---|---|---|
| **SUNU card** | Press **Collect premium**, hold the card against the reader | Normal case |
| **QR code (virtual card)** | Press **Scan QR**, frame the code on the client's phone or on their policy schedule | Client has no physical card |
| **Manual search** | Press **Search**, enter policy number, name or phone | Card lost or unreadable |

**Step 2 — Check the client.** The screen shows the insured's name and their **list of active policies**. Check the name aloud with the client.

**Step 3 — Choose the policy.** Tap the policy being paid. If the client has several policies, ask them which one — do not guess. The screen shows the expected premium and the next due date.

**Step 4 — Enter the amount.** Type the amount received. The expected premium is suggested; you can change it if the client pays a different amount.

**Step 5 — Choose the payment method.**

| Method | What happens |
|---|---|
| **Cash** | You take the money. **It counts against your cap.** |
| **Mobile Money** | Enter the client's number. **The client receives a prompt on their own phone and enters their own PIN.** Wait for confirmation on your screen. **This does not count against your cap** — the money goes straight to SUNU |

> **Never ask a client for their Mobile Money PIN, and never type it yourself.** The client enters it on their own handset. Anyone asking for that PIN is committing fraud.

**Step 6 — Receipt.** The terminal prints the receipt. **Hand it to the client.** For Mobile Money, the receipt prints **only after the payment is confirmed** — if it is not confirmed, no receipt, and no money has moved.

## A.6 Reprinting a receipt

**My day → find the collection → Reprint.** The copy is marked **DUPLICATA**. Every reprint is recorded. Use it when the printer jams or the paper tears — not to produce a second "original".

## A.7 Following your day

**My day** shows: number of collections, total collected, split cash / Mobile Money, list of the day's operations, and items still to be synchronised.

Check it before finishing your day. **The cash in your bag must equal the cash total shown.** If it does not, do not go home without telling your supervisor.

## A.8 Paying in (releasing your cap)

*Do this when the bar goes orange, and always before it goes red.*

**Step 1.** Physically deposit the money: either at the bank into the designated SUNU account, or at a SUNU cash office.

**Step 2.** On the terminal: **Pay in** →
- amount deposited;
- method (bank deposit / cash office);
- bank reference or slip number;
- **photograph of the deposit slip** (strongly recommended — it speeds up confirmation).

**Step 3.** Submit. Status becomes **Declared, awaiting confirmation**.

**Step 4.** The supervisor checks it against the bank statement or the cash count and confirms. Your balance drops, the bar goes green, and collection is unblocked. This is usually the same day; it depends on when the bank statement is available.

> **Declaring a pay-in does not by itself release your cap.** Only the supervisor's confirmation does. So pay in *before* you are blocked, not after.

## A.9 Working without network

Everything below works offline:

| Works offline | Needs network |
|---|---|
| Logging in (within the offline period after your last online login) | The very first login on a new terminal |
| Full subscription capture, photos, GPS | Searching for a client not in your local cache |
| Collecting from a card or QR (client in cache) | Mobile Money payments |
| Printing receipts | Confirming a pay-in |
| Consulting your day | |

The ⟳ counter shows what is waiting. When you have network again, everything leaves automatically. **Do not reinstall the application and do not clear the application data while items are waiting** — you would destroy collections that have not yet reached the back office. If the counter refuses to clear for more than a day, call support.

## A.10 What you must never do

- Never collect money without printing a receipt.
- Never keep a client's card.
- Never ask for a client's Mobile Money PIN.
- Never lend your terminal or your PIN.
- Never exceed the time allowed to pay in.
- Never promise a client anything about their contract — that is the back office's job, not yours.

## A.11 If something goes wrong

| Situation | What to do |
|---|---|
| Terminal lost or stolen | **Call your supervisor immediately.** The account is disabled remotely. There is no cash on the device |
| Terminal broken | Report it; a spare is assigned. Collections not yet sent are recovered if the device is repaired |
| Card unreadable | Use manual search; report the card so a replacement is issued |
| Client contests a payment | Reprint the receipt, note the reference, refer to the back office. Do not repay from your own pocket |
| Money collected but the client left before the receipt printed | Note the reference, call the supervisor the same day. **Do not delete anything** |
| Wrong amount entered | Do not attempt to fix it yourself. Report it: the back office issues a correction entry |

## A.12 Pocket card — to print and keep

```
 ┌────────────────────────────────────────────────┐
 │  SUNU — COLLECTION AGENT · QUICK REFERENCE      │
 ├────────────────────────────────────────────────┤
 │  NEW CLIENT                                     │
 │   Paper proposal signed → New subscription →    │
 │   product → proposal no. → identity →           │
 │   3 photos → GPS → policy costs (receipt 1) →   │
 │   first premium (receipt 2)                     │
 │                                                 │
 │  EXISTING CLIENT                                │
 │   Collect premium → card / QR / search →        │
 │   check the NAME → choose the POLICY →          │
 │   amount → cash or MoMo → receipt               │
 │                                                 │
 │  THE BAR                                        │
 │   green = carry on · orange = pay in today ·    │
 │   red = blocked, pay in now                     │
 │                                                 │
 │  ALWAYS                                         │
 │   ✓ a receipt for every payment                 │
 │   ✓ check the name before collecting            │
 │   ✓ pay in before you are blocked               │
 │   ✗ never ask for a client's MoMo PIN           │
 │   ✗ never lend your terminal or PIN             │
 │                                                 │
 │  Support: ____________  Supervisor: __________  │
 └────────────────────────────────────────────────┘
```

---

# PART B — BACK-OFFICE AGENT

*You process what comes in from the field: subscription files, collections, exports, client records.*

## B.1 Logging in and your dashboard

Open the back-office address in your browser, enter your credentials. 〔SCREEN: dashboard〕

Your dashboard shows: today's collections and totals, **proposals awaiting review** (your main queue), active collectors and their cap status, exceptions to handle, and the last exports.

## B.2 Reviewing subscription files — your core task

**Menu: Subscriptions → Awaiting review.** Each line is a file sent from the field.

For each file:

1. Open it. On the left, the field data; on the right, the photographs.
2. **Check the photographs.** Is the proposal readable? Is it signed? Is the CNIB readable on both sides?
3. **Check consistency.** Does the name on the proposal match the name entered? Does the proposal number match the paper document? Does the product match?
4. **Check the payments.** Policy costs and first premium present, and consistent with the product's tariff?
5. **Check for a duplicate.** The system flags a possible existing client by CNIB or phone. If it is the same person, use **Merge** rather than creating a second record.

Then decide:

| Decision | Effect |
|---|---|
| **Accept** | Policy created, card issuance queued (Part D), file closed |
| **Request correction** | Returned to the collector with your comment (e.g. "CNIB photo unreadable") |
| **Reject** | File closed with a mandatory reason. **The collections already made are not cancelled** — money received stays recorded. Any refund is a separate business decision |

> **Never accept a file whose photographs you cannot read.** The photograph is the proof of subscription. Ask for a correction — it costs the collector two minutes and saves a dispute later.

## B.3 Consulting collections

**Menu: Collections.** Filter by date, collector, product, policy, payment method, amount, status.

Each collection shows: date and time, collector, terminal, insured, policy, amount, method, receipt number, unique reference, GPS position where captured, and the SUNULIFE transmission status.

Useful views: *Today*, *This collector*, *Mobile Money pending*, *Not yet transmitted to SUNULIFE*.

## B.4 Excel export — recording into SUNULIFE

*This is the operation the TdR requires explicitly.*

1. **Menu: Reports → Collections.**
2. Set the period and any filters (collector, product, payment method).
3. Choose the **Excel (XLSX)** format.
4. **Generate**. The report is produced in the background — you can keep working.
5. When status shows **Ready**, click **Download**.

The file contains one row per collection with the fields SUNULIFE requires: date, product, policy number, proposal number, insured, amount, payment method, receipt number, collector reference, terminal, unique reference.

**Good practice:** export **once a day at a fixed hour**, over a period that does not overlap the previous export, and record in SUNULIFE the same day. Keep the file. When the automatic SUNULIFE integration is activated (Document 1 §8.4), this export remains available as a control and a fallback — it does not disappear.

## B.5 Managing clients and policies

**Menu: Clients.** Search by name, phone, CNIB, policy or card number.

A client record shows identity, documents, policies, cards (physical and virtual), and full collection history.

| Action | Note |
|---|---|
| Correct identity data | Audited; correct a typing error, do not overwrite a different person |
| Add a document | For a replacement CNIB, for example |
| Merge two records | Only when it is certainly the same person; the operation is traced and reversible only by IT |
| Request a card | Sends the request to the card officer (Part D) |
| Issue a virtual card (QR) | Immediate; sent by SMS or printed |

**Data protection.** You see personal data because your job requires it. Do not export it beyond what you need, do not send it outside SUNU, and do not discuss it outside your role. Every export is logged with your name.

## B.6 Exception queues

**Menu: Exceptions.**

| Queue | What it means | What you do |
|---|---|---|
| **Mobile Money pending** | Payment neither confirmed nor failed | Wait for the reconciliation; if still pending after 24 h, escalate to IT |
| **SUNULIFE rejected** | The push was refused | Read the error, correct the data if it is a data problem, resubmit; escalate if it is technical |
| **Possible duplicate** | Two client records look like the same person | Compare and merge, or dismiss |
| **Unmatched collection** | Collection with no valid policy link | Identify the correct policy and re-link |

Handle these **daily**. They do not resolve themselves and they grow.

---

# PART C — SUPERVISOR / CONTROLLER

*You are the financial control. Your signature releases collectors and confirms that SUNU's money has arrived.*

## C.1 Your dashboard

Shows: **pay-ins awaiting confirmation** (your priority), all collectors with cap utilisation, collectors blocked, variance alerts, ageing of uncollected balances, day and week totals.

## C.2 Confirming a pay-in — your critical responsibility

**Menu: Pay-ins → Awaiting confirmation.**

For each declaration you see: collector, declared amount, method, bank reference, deposit-slip photo, declaration date and time, and the collector's balance before and after.

**Procedure:**

1. **Verify against the source.** Bank deposit → find the credit on the bank statement. Cash office → obtain the counting record from the cashier.
2. **Compare** the amount actually received with the amount declared.
3. Then:

| Case | Action |
|---|---|
| Amounts match | **Confirm**. The balance drops, the terminal is released |
| Received *less* than declared | **Confirm the real amount.** The variance is recorded against the collector and reported. Inform your line manager the same day |
| Received *more* than declared | Confirm the real amount; note the reason |
| Nothing received | **Reject** with a reason. The collector stays blocked. Escalate immediately |

> **Confirm only against evidence.** Confirming without seeing the bank credit or the counting record means declaring that SUNU has money it may not have. Above a configured amount, a second approver is required — this protects you as much as it protects SUNU.

## C.3 Monitoring caps

**Menu: Collectors → Caps.**

| Signal | Reading | Action |
|---|---|---|
| Collector permanently near their cap | Cap too low, or paying in too rarely | Review the cap, or the pay-in rhythm |
| Balance ageing beyond the policy limit | Cash held too long | Call the collector today |
| Collector blocked for more than a day | Blocked work | Chase the pay-in or the confirmation |
| Collections with no matching pay-in over a long period | **Warning sign** | Investigate at once |

## C.4 Fraud indicators to watch weekly

| Indicator | Possible reading |
|---|---|
| Gap in receipt numbering on a terminal | Suppressed or destroyed receipts |
| Collections clustered at one GPS point far from any client | Fictitious collections |
| Collections recorded at implausible hours | Irregular practice |
| Repeated variances on one collector's pay-ins | Diversion |
| Frequent reprints on the same collections | Duplicate receipts handed out |
| Many corrections requested by one collector | Poor practice or manipulation |

The system raises these automatically. **Treat an alert as a question to ask, not an accusation.** Most have an innocent explanation; the ones that do not must be escalated in writing the same day.

## C.5 Reconciliation

**Weekly:** total collected − total confirmed pay-ins = sum of uncollected balances. If it does not balance, escalate to IT with the period concerned.

**Daily for Mobile Money:** platform total versus the provider's settlement report versus the amount credited to the bank account. Any discrepancy goes to the exception queue.

---

# PART D — CARD ISSUANCE OFFICER

*You produce and issue SUNU cards, physical and virtual.*

## D.1 Card stock

**Menu: Cards → Stock.** Blank cards are recorded and each card number is traceable from stock to insured.

| Task | Frequency |
|---|---|
| Record a new delivery of blanks | On receipt |
| Physical count against the system record | Monthly |
| Report any discrepancy | Immediately — it is a security incident |

## D.2 Issuing a physical card

**Menu: Cards → Requests.** Each request comes from an accepted subscription.

1. Open the request. Check the insured's name and the **list of policies** — the card must carry every active policy of that insured, as the TdR requires.
2. **Allocate a card** from stock. The system assigns the number and the expiry.
3. **Generate the personalisation file** — this contains the SUNU branding block, the insured's last and first name, the card number, the expiry, and the data to encode.
4. On the personalisation station: **print** and **encode** the card.
5. Check the printed card: name spelled correctly, number legible, branding correct.
6. Back in the system: **Mark as written**. The card becomes active.
7. Hand over or dispatch the card, and record the handover.

> **Check the spelling of the name before printing.** A misprinted card is a wasted card and a client who feels badly treated.

## D.3 Issuing a virtual card (QR)

**Menu: Clients → [client] → Issue virtual card.**

Choose the delivery: **SMS** to the client's number, **print** on the policy schedule, or **both**. The QR is generated immediately and works on any terminal, including offline.

Use it when: the client is waiting for their physical card, has lost it, or lives far from a personalisation point. It is also the fastest way to get a new client operational on the day they subscribe.

## D.4 Blocking, replacing, renewing

| Situation | Action | Effect |
|---|---|---|
| Card lost or stolen | **Block** with the reason | Immediately unusable; blocking is instant server-side |
| Card damaged | **Replace** | New card linked to the old one; the old card permanently dead |
| Card expiring | **Renew** | New card with a new expiry |
| Client adds a policy | **Update card** | Re-encode, or issue an updated virtual card |

Blocking a card **never** blocks the client's policies or their payments — the client can still pay via manual search or a QR.

## D.5 Data protection at the station

The personalisation station handles client names and card data. Keep the room access-controlled, do not leave personalisation files on the workstation after use, and never take card data out of SUNU premises.

---

# PART E — ADMINISTRATOR (SUNU)

*You configure the solution and manage who can do what.*

## E.1 Users and profiles

**Menu: Users.**

Creating a user: identity and e-mail → **assign exactly one profile** (Collection Agent, Back-Office Agent, Supervisor, Card Officer, Administrator) → temporary password issued separately from the username → the user changes it on first login.

> **Assign the minimum profile that lets the person do their job.** A back-office agent does not need supervisor rights "just in case". If someone needs an occasional extra action, that is a request to the supervisor, not a permanent permission.

**Departures — the same day:** disable the account (never delete it — deletion would break the audit trail), close the terminal assignment, ensure all cash is paid in and confirmed, recover any cards.

**Every quarter:** review the full list of accounts and permissions with each department head, disable what is unused, and record that you did it.

## E.2 Collectors

**Menu: Agents.** Create the collector, assign a terminal, set the cap.

Setting a cap is a business judgement:

| Too low | Too high |
|---|---|
| Collector blocked constantly | Large amount exposed if the collector is dishonest or robbed |
| Wasted travel to pay in | Weak control |

Start conservatively — a cap covering roughly one to two days of normal collection for that collector's round — and adjust from real data after a month.

## E.3 Terminals

**Menu: Terminals.** Register each device with its serial number, SIM, alias and model; assign it to a collector; keep spares in stock. On loss or theft: mark it, disable the collector's account, issue a spare.

## E.4 Products

**Menu: Products.** For each insurance product: code, French and English label, premium frequency, policy-cost rule, receipt template, and the SUNULIFE product code.

Changing a product's tariff affects future collections only. **Never delete a product that has collections** — deactivate it instead.

## E.5 Fees, commissions and limits

The platform supports fixed, percentage and tiered rules for transaction costs and for collector commissions, definable per product and per transaction type. Configure these with Finance; every change is audited and applies going forward.

## E.6 Notifications

**Menu: SMS.** Configure the sender name, monitor the credit balance, and choose which events notify the insured. Recommended as a minimum: **an SMS confirmation to the insured for every premium collected** — it is the most effective control in the whole solution against a collector pocketing cash.

## E.7 What you must not do

- Do not give yourself pay-in confirmation rights. Configuration and financial confirmation must stay in different hands.
- Do not create shared or generic accounts ("agent1", "backoffice"). Every account belongs to one named person.
- Do not reuse a departed employee's account for their replacement. Create a new one.

---

# PART F — IT / TECHNICAL PROFILE

*You keep the platform running and the integrations healthy.*

## F.1 Scope

Environment configuration, integrations and their credentials, technical monitoring, backups, releases, and level-2 support. **You do not confirm pay-ins** — technical power must not equal financial power (Document 2 §5.2).

## F.2 Integrations

**Menu: Configuration → Integrations.**

| Integration | What you manage |
|---|---|
| **SUNULIFE** | Endpoint, authentication, field mapping, sync schedule, sync log and replay |
| **Mobile Money** | Provider, credentials, callback URL, reconciliation |
| **SMS** | Provider, sender, credits |

Credentials are entered once and are not displayed again. Rotate them at least annually and immediately on any suspicion, following the documented procedure (Document 2 §5.4).

## F.3 Daily checks

- Overnight backup completed.
- Queue workers running; no queue backing up.
- SUNULIFE sync: last successful run, error count.
- Mobile Money: pending payments and reconciliation status.
- Error rate and response times.
- Security alerts.

## F.4 Monthly and quarterly

**Monthly:** dependency vulnerability scan; apply patches in the agreed window; review privileged account access; check storage growth.
**Quarterly:** **full restore test from backup into a clean environment** — a backup that has never been restored is not a backup; review the alert configuration; review the audit trail for anomalies.

## F.5 Releases

Every release: tested in UAT, approved by SUNU, deployed in the agreed window, changelogged, with a documented rollback. Never deploy directly to production. Never use real production data in UAT.

## F.6 Level-2 support

| Symptom | First checks |
|---|---|
| A terminal cannot synchronise | Agent active? Terminal assigned? API key valid? Rate limit hit? Sync log |
| Mobile Money payments stuck pending | Provider status, callback reachability, signature verification, reconciliation job |
| SUNULIFE sync failing | Endpoint reachable, credentials valid, payload validation errors in the sync log |
| Export never completes | Queue worker running, disk space, report job errors |
| A user cannot log in | Account active, not locked, correct profile, MFA device |

---

# PART G — TROUBLESHOOTING (all profiles)

| Problem | Likely cause | Solution |
|---|---|---|
| Terminal will not print | Paper, jam, cover open, battery | Reload paper, clear the jam, close the cover, charge. Then **Reprint** |
| Card not read | Card damaged, moved too fast, wrong side | Hold it flat and still against the reader. If it still fails, use manual search and report the card |
| QR not scanned | Screen too dark, dirty lens, damaged print | Raise the phone's brightness, clean the lens, or use manual search |
| GPS will not fix | Indoors, poor sky view | Step outside for a moment; if it still fails, continue — you are not blocked |
| "Cap reached" | You are holding too much cash | Pay in (§A.8) |
| "Terminal not assigned" | Wrong terminal, or assignment not made | Contact the administrator |
| Sync counter will not clear | No network, or a server problem | Move to coverage. If it persists more than a day, call support |
| Mobile Money stays pending | Provider slow or unreachable | Wait; do not collect twice. It resolves by reconciliation |
| Export stuck at "Generating" | Queue congestion | Wait a few minutes; if it persists, call IT |
| Account locked | Too many wrong attempts | Wait, then retry. Otherwise ask the administrator for a reset |

**When you call support, have ready:** your name and profile, the terminal serial number if applicable, the exact time, the operation reference or receipt number, and the exact error message.

---

# PART H — GLOSSARY

| Term | Meaning |
|---|---|
| **Cap** | The maximum amount of collected cash a collector may hold before being required to pay in |
| **CNIB** | Carte Nationale d'Identité Burkinabè — the national identity card |
| **Collection** | A premium or policy-cost payment received from an insured |
| **DUPLICATA** | The mark printed on a reprinted receipt |
| **Insured** | The person covered by the policy |
| **Pay-in** | The act of handing collected cash to the bank or the SUNU cash office |
| **Policy costs** | The charges payable at subscription, separate from the premium |
| **POS / TPE** | The payment terminal used in the field |
| **Premium** | The amount the insured pays for their cover |
| **Proposal** | The signed subscription form, and the record created from it |
| **Reference (unique)** | The identifier generated by the terminal for each operation; it makes double-counting impossible |
| **Reference period** | The period a premium covers (e.g. September 2026) |
| **SUNULIFE** | SUNU's core business software, master of customers and contracts |
| **Sync / synchronisation** | The sending of data held on the terminal to the back office |
| **UAT** | User acceptance testing — the phase where SUNU tests before go-live |
| **Virtual card** | The QR code that identifies an insured in place of a physical card |

---

## Annex — Training plan

| Profile | Duration | Content | Participants (pilot) |
|---|---|---|---|
| Collection Agent | 1 day | Parts 0 and A, hands-on with real terminals, full role-play of both flows, fraud and security rules | 15 |
| Back-Office Agent | ½ day | Parts 0 and B, review of real files, exports | 3–5 |
| Supervisor | ½ day | Parts 0, C and B.3, pay-in confirmation on real cases | 2–3 |
| Card Officer | ½ day | Parts 0 and D, hands-on personalisation station | 1–2 |
| Administrator | ½ day | Parts 0 and E | 1–2 |
| IT | 1 day | Part F plus Documents 1 and 2 | 1–2 |

Training is delivered on the UAT environment with synthetic data, before the pilot. Each participant keeps their part of this guide; collectors also keep the pocket card of §A.12.

---

*End of Document 3. Screenshots are inserted after UAT screen freeze.*
