# Transaction Costs and Commissions

A short guide for agents, supervisors, and administrators.

---

## Overview

Every transaction involves these amounts:

| Term | Meaning |
|------|---------|
| **Transaction amount** | The principal (e.g. money deposited or withdrawn). |
| **Transaction cost** | The **service fee** charged for the operation. |
| **Commission** | The **agent’s share** of the fee. |
| **Business share** | The **organization’s share** of the fee. |

**Key rules:**

- Commission is calculated from the **transaction cost (fee)**, not the transaction amount.
- **Commission + Business share = Transaction cost** (always).
- By default, the **customer pays the fee**.

---

## Transaction costs (fees)

Configured per **transaction type** and **business unit**.

### Fixed

Same fee for every transaction, regardless of amount.

| Transaction amount | Fee |
|--------------------|-----|
| 1,000 | 100 |
| 10,000 | 100 |
| 50,000 | 100 |

*Example: withdrawal fee = 100 (always).*

### Tiered fixed

Fee depends on the **amount band**. Each band has its own fixed fee.

| Transaction amount | Fee |
|--------------------|-----|
| 0 – 10,000 | 100 |
| 10,000.01 – 50,000 | 200 |
| 50,000.01 and above | 350 |

| Transaction | Band | Fee |
|-------------|------|-----|
| 5,000 | 0 – 10,000 | 100 |
| 25,000 | 10,000.01 – 50,000 | 200 |
| 80,000 | 50,000.01+ | 350 |

### Tiered percentage

Fee depends on the amount band. Each band uses a **different percentage** of the transaction amount.

| Transaction amount | Rate |
|--------------------|------|
| 0 – 10,000 | 1% |
| 10,000.01 and above | 0.5% |

| Transaction | Calculation | Fee |
|-------------|-------------|-----|
| 8,000 | 1% × 8,000 | 80 |
| 20,000 | 0.5% × 20,000 | 100 |

### Percentage (non-tiered)

A single percentage of the transaction amount.

*Example: 1% fee → 5,000 transaction = 50 fee.*

---

## Commissions (agent share)

Configured per **transaction type** and **business unit**. Always derived from the **fee**, not the principal.

### Fixed commission

Same commission every time.

*Example: 30 per transaction — whether the fee is 100 or 250.*

### Percentage commission

A percentage of the **transaction cost**.

*Example: 40% of fee → fee 100 = commission 40; fee 250 = commission 100.*

### Tiered fixed commission

Used with **tiered transaction costs**. Each amount band has a fixed commission. Bands match the cost tiers.

| Amount band | Fee | Fixed commission |
|-------------|-----|------------------|
| 0 – 10,000 | 100 | 15 |
| 10,000.01 – 50,000 | 200 | 35 |
| 50,000.01+ | 350 | 50 |

### Tiered percentage commission

Used with **tiered transaction costs**. Each band has a different percentage **of the fee**.

| Amount band | Fee | Commission rate | Agent earns |
|-------------|-----|-----------------|-------------|
| 0 – 10,000 | 100 | 10% of fee | 10 |
| 10,000.01+ | 250 | 20% of fee | 50 |

> **Note:** Tiered commission requires a tiered transaction cost for the same type. Commission tiers must match the number of cost tiers.

---

## How a transaction is affected

### Cash withdrawal

**Setup:** fee 100, commission 30, business share 70.

**Customer withdraws 10,000:**

| Account | Change |
|---------|--------|
| Customer account | −10,100 (10,000 + 100 fee) |
| Agent float | +10,000 |
| Agent commission account | +30 |
| Business fee account | +70 |

### Cash deposit

**Setup:** fee 50, commission 20, business share 30.

**Customer deposits 5,000:**

| Account | Change |
|---------|--------|
| Agent float | −5,000 |
| Customer account | +5,000, then −50 fee → **net +4,950** |
| Agent commission account | +20 |
| Business fee account | +30 |

---

## Quick reference

```
Transaction amount  →  Transaction cost (fee)
Transaction cost    →  Commission (agent)
Transaction cost    −  Commission  =  Business share (organization)
```

| Cost / commission type | Based on | Changes with amount? |
|------------------------|----------|----------------------|
| Fixed | Flat value | No |
| Tiered fixed | Amount band → flat value | Yes, by band |
| Tiered percentage | Amount band → percentage | Yes, by band |
| Percentage | Single rate on amount or fee | Yes (proportionally) |

---

## Configuration (administrators)

1. Enable the **transaction type** (e.g. Cash Deposit, Cash Withdrawal).
2. Set **transaction cost** (fixed, percentage, or tiered).
3. Set **commission** for the same type.
4. For tiered commission, configure **tiered cost first**.
5. Use the **cost preview** and **commission calculator** under **Configuration** to test before going live.

Each business unit can have its own rules for the same transaction type.
