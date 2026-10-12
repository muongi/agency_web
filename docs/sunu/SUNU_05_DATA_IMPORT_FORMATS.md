# SUNU Assurances Vie Burkina Faso
## Mobile Premium Collection Solution on POS Terminals
### Document 5 — Data Import and Exchange Formats

| Field | Value |
|---|---|
| Document | Data Import and Exchange Formats Specification |
| Version | 1.0 |
| Date | 17 September 2026 |
| Prepared for | SUNU Assurances Vie Burkina Faso — Central Operations Department and IT |
| Prepared by | DBS SA — Digital Business Services |
| Accompanying files | `SUNU_client_import_sample_EN.csv` · `SUNU_import_clients_exemple_FR.csv` |
| Related documents | Doc 1 — Solution Architecture (§6, §7); Doc 2 — Security Architecture & Procedures |

---

## 1. Purpose and Scope

This document describes the files and data formats the solution works with, and what a file must look like to be imported without errors. It covers:

- the **customer and policy import file**, used to load SUNU's existing customers and their policies into the back office from a SUNULIFE extract, so that collectors see them on the terminals from the first day;
- the other data formats the solution exchanges: the terminal API, subscription images and the premium collection export.

It describes the version currently running in the pilot.

---

## 2. Supported Formats at a Glance

| Exchange | Direction | Format | Encoding | Channel |
|---|---|---|---|---|
| Existing customers and policies | SUNU → back office | CSV (`.csv`), comma-separated | UTF-8 without BOM | Web upload by a back-office user |
| Premium collections | Back office → SUNU | CSV (`.csv`), comma-separated | UTF-8 | Web download |
| Subscription images | Terminal → back office | JPEG, PNG or WebP | Binary, multipart/form-data | Terminal API |
| Terminal API data | Terminal ↔ back office | JSON | UTF-8 | REST API |

> **Excel workbooks (`.xlsx`) are not read by the import.** Save the workbook as CSV first, following section 3.8.

---

## 3. Customer and Policy Import File

### 3.1 How the import works

A back-office user opens **Premium collection → Phase 0 import** (`/business/sunu/import`), selects the file and clicks **Import**.

Each data row describes **one policy** and the customer who holds it. For every row, the back office:

1. Looks up the product by its code. If the code is unknown, the row is rejected with the message `Unknown product: <code>`.
2. Finds the customer with the same last name, first name and phone number, or creates one.
3. Creates the policy, or updates it if a policy with the same policy number already exists.
4. Sets the policy status to **active** and its source to **SUNULIFE**, and stamps the import date and time.

When the file is processed, the screen shows the number of rows imported and the first 20 error messages. Rows without errors are imported even if other rows fail.

```
 SUNULIFE extract ──▶ CSV file ──▶ Back office: Import
                                     │
                    for each row ────┤
                                     ├─ product_code  → insurance product (must exist)
                                     ├─ last_name + first_name + phone → customer (found or created)
                                     └─ policy_number → policy (created or updated), status active
                                     │
                                     ▼
                     "N rows imported" + first 20 errors
```

### 3.2 File format requirements

| Property | Requirement |
|---|---|
| File type | Plain-text CSV, extension `.csv` |
| Character encoding | UTF-8 **without** byte-order mark (BOM). Accented names such as `Rasmané` or `Noëlle` are supported |
| Field separator | Comma `,` — semicolons are **not** accepted |
| Text qualifier | Double quote `"`, needed only when a value contains a comma or a quote |
| Decimal separator | Point `.` — for example `5000` or `5000.00` |
| Thousands separator | None — `5000`, not `5 000` or `5,000` |
| Date format | ISO 8601 `YYYY-MM-DD` — for example `2025-03-01` |
| Line ending | CRLF (Windows) or LF (Linux) |
| First line | Column headers (section 3.3) |
| Column order | Free; columns are recognised by header name |
| Header case | Free; `Policy Number`, `POLICY_NUMBER` and `policy_number` are equivalent |
| Header accents | **Not allowed** — write `prenom`, not `prénom` |
| Rows per file | Up to 5 000 recommended; split larger extracts into several files |

### 3.3 Columns

| Column (English header) | Accepted French header | Required | Format | Example | Rule |
|---|---|---|---|---|---|
| `policy_number` | `numero_police` | **Yes** | Text, max. 255 | `MER-2025-000101` | Unique policy number. Importing the same number again updates that policy |
| `product_code` | `code_produit` | **Yes** | Text, product code | `MICRO_EPARGNE_RETRAITE` | Must be one of the codes in section 3.4 |
| `last_name` | `nom` | **Yes** | Text, max. 255 | `OUEDRAOGO` | Customer's last name |
| `first_name` | `prenom` | **Yes** | Text, max. 255 | `Aminata` | Customer's first name(s) |
| `phone` | `telephone` | **Yes** | Text, 8 digits | `70112233` | National format without spaces or prefix. Used to recognise the customer |
| `premium_amount` | — | Optional | Number, XOF | `5000` | Premium per period. If the column is absent, the product default applies |
| `premium_frequency` | — | Optional | Code (section 3.5) | `monthly` | If the column is absent, the product default applies |
| `effective_date` | — | Optional | `YYYY-MM-DD` | `2025-03-01` | Policy effective date. If the column is absent, the import date is used |
| `sunulife_policy_id` | — | Optional | Text, max. 255 | `SL-778101` | Internal SUNULIFE identifier, kept for the future API synchronisation |

The last four columns have English headers only; French and English headers can be mixed in the same file.

> **Empty cells in optional columns.** When an optional column is present, fill it on every row. To use the product default for all rows, leave the column out of the file entirely. An empty `premium_amount` or `effective_date` cell causes that row to be rejected.

### 3.4 Product codes

| product_code | Product (French) | Product (English) | Category | Pilot default premium |
|---|---|---|---|---|
| `MICRO_EPARGNE_RETRAITE` | Micro Épargne Retraite | Micro Retirement Savings | Retraite | 5 000 XOF monthly |
| `MIXTE_PLUS_HORIZON` | Mixte Plus Horizon | Mixte Plus Horizon | Projet | Not set — provide `premium_amount` |
| `RENTE_EDUCATION_PLUS` | Rente Éducation Plus | Education Annuity Plus | Education | Not set — provide `premium_amount` |
| `MICRO_QUIETUDE` | Micro Quiétude | Micro Quietude | Funérailles | 2 000 XOF monthly |

Codes are written in capitals, exactly as shown. Default premiums are those configured for the pilot and can be changed in the back office. Products added later in **Premium collection → Products** can be imported with the code entered there.

### 3.5 Premium frequency codes

| premium_frequency | Meaning |
|---|---|
| `daily` | Daily / Journalière |
| `weekly` | Weekly / Hebdomadaire |
| `monthly` | Monthly / Mensuelle |
| `quarterly` | Quarterly / Trimestrielle |
| `annual` | Annual / Annuelle |

### 3.6 Re-importing and duplicates

| Situation | Result |
|---|---|
| Same `policy_number` imported again | Existing policy updated; no duplicate created |
| Same customer (last name, first name, phone) on several rows | One customer holding several policies |
| Same customer with a different phone number or spelling | A second customer is created; keep spellings and phone numbers consistent across the extract |
| Row rejected | The policy on that row is not written; its customer may already have been created and is reused on the next import. Correct the row and import the file again; rows already imported are updated, not duplicated |

Re-running a corrected file is therefore safe.

### 3.7 Common errors and how to avoid them

| What you see | Cause | How to fix |
|---|---|---|
| 0 rows imported, no errors | File saved with semicolons (`;`), the default of Excel in French | Save with comma separators (section 3.8) |
| Every row rejected, first column seems missing | File saved as "CSV UTF-8" from Excel, which adds a byte-order mark | Save without BOM (section 3.8) |
| First names or phones all empty or shown as "Client" | Accented header such as `prénom` or `téléphone` | Use `prenom`, `telephone`, or the English headers |
| `Unknown product: …` | Product code misspelt or not created | Use a code from section 3.4 |
| Row rejected with a database message about a date | Date written `01/03/2025` or cell empty | Use `2025-03-01`, or remove the column |
| Row rejected with a database message about a decimal value | Amount written `5 000` or `5000,00`, or cell empty | Use `5000` or `5000.00`, or remove the column |
| Phone shown as `7.01E+07` or without leading digits | Spreadsheet converted the phone to a number | Format the phone column as Text before saving |
| Import stops with a server error | A row has more cells than the header line, often a comma inside an unquoted value. Rows before it are already imported | Put values containing commas in double quotes, then import the whole file again |

### 3.8 Preparing the file

**Recommended — LibreOffice Calc**

1. Open the extract.
2. **File → Save As**, file type **Text CSV (.csv)**, tick **Edit filter settings**.
3. Character set **Unicode (UTF-8)**, field delimiter **`,`**, string delimiter **`"`**.

LibreOffice writes UTF-8 without BOM.

**Google Sheets** — **File → Download → Comma-separated values (.csv)**. The file is UTF-8 without BOM and comma-separated.

**Microsoft Excel** — Excel in French saves CSV with semicolons, and its "CSV UTF-8" option adds a BOM, so neither option produces a valid file directly. Keep the workbook in Excel if convenient, then open it in LibreOffice Calc or upload it to Google Sheets and save the CSV from there.

**Directly from SUNULIFE or a database** — export as UTF-8, comma-separated, with headers from section 3.3.

### 3.9 Data not carried by the import file

The file carries the data needed for collection: customer name, phone and policy details. Identity documents, date of birth, address and photographs are not imported in this version; they are captured on the terminal when a customer subscribes. Other fields from SUNULIFE can be added to the file later if they are needed.

### 3.10 Data protection

The import file contains personal data. Transfer it through a channel agreed with SUNU IT (not ordinary e-mail), delete working copies after the import, and give access to the Phase 0 import screen only to authorised back-office profiles. See Document 2.

---

## 4. Sample Files

Two sample files accompany this document. They contain **fictitious** customers and policy numbers and use the four pilot products.

| File | Headers | Rows |
|---|---|---|
| `SUNU_client_import_sample_EN.csv` | English headers | 6 |
| `SUNU_import_clients_exemple_FR.csv` | French headers for the five required columns, English headers for the optional ones | 6 |

Content of the English sample:

```
policy_number,product_code,last_name,first_name,phone,premium_amount,premium_frequency,effective_date,sunulife_policy_id
MER-2025-000101,MICRO_EPARGNE_RETRAITE,OUEDRAOGO,Aminata,70112233,5000,monthly,2025-03-01,SL-778101
MER-2025-000102,MICRO_EPARGNE_RETRAITE,SAWADOGO,Issouf,76223344,10000,monthly,2025-06-15,SL-778102
MQT-2024-000517,MICRO_QUIETUDE,KABORE,Marie-Noëlle,65334455,2000,monthly,2024-11-01,SL-640517
MQT-2026-000033,MICRO_QUIETUDE,ZONGO,"Adama Jean-Baptiste",71445566,6000,quarterly,2026-01-01,SL-640533
MPH-2025-000009,MIXTE_PLUS_HORIZON,TRAORE,Fatimata,78556677,25000,monthly,2025-09-01,SL-812009
REP-2023-000245,RENTE_EDUCATION_PLUS,COMPAORE,Rasmané,74667788,120000,annual,2023-10-01,SL-590245
```

The French sample has the same rows under this header line:

```
numero_police,code_produit,nom,prenom,telephone,premium_amount,premium_frequency,effective_date,sunulife_policy_id
```

The smallest valid file uses only the five required columns; premium amount, frequency and effective date then come from the product defaults and the import date:

```
policy_number,product_code,last_name,first_name,phone
MQT-2024-000517,MICRO_QUIETUDE,KABORE,Marie-Noëlle,65334455
```

The policy number formats in the samples are illustrative. Use the policy numbers as they exist in SUNULIFE.

---

## 5. Other Data Formats

### 5.1 Premium collection export

**Premium collection → Premium collections → Export Excel/CSV (SUNULIFE)** downloads a CSV file named `sunu_premiums_YYYYMMDD_HHMMSS.csv`, comma-separated, one row per collection, most recent first.

| Column | Content |
|---|---|
| `Reference` | Unique collection reference generated by the terminal |
| `Kind` | `policy_cost`, `first_premium` or `recurring_premium` |
| `Amount` | Amount in XOF, point as decimal separator |
| `Policy` | Policy number, or `-` |
| `Proposal` | Proposal number for a first premium, or `-` |
| `Product` | Product code |
| `Client` | Last name and first name |
| `Agent` | Collector name |
| `Instrument` | `cash`, `momo` or `bank` |
| `Collected At` | `YYYY-MM-DD HH:MM:SS` |
| `Receipt` | Receipt number, or `-` |

### 5.2 Subscription images

Each subscription sent from a terminal carries three images: the signed proposal form, the CNIB front and the CNIB back. The terminal compresses each image to at most 900 KB before sending. The back office accepts JPEG, PNG and WebP and stores one folder per proposal.

### 5.3 Terminal API

The terminal and the back office exchange JSON over a REST API; subscriptions with images use `multipart/form-data`. Common conventions:

| Data | Format |
|---|---|
| Text | UTF-8 |
| Amounts | Number in XOF, stored with 2 decimals |
| Dates and times | `YYYY-MM-DD` and `YYYY-MM-DD HH:MM:SS` |
| GPS | Decimal degrees, 7 decimals (`12.3714277`, `-1.5196603`) |
| Errors | HTTP 422 with `message` and an `errors` object listing each field |

The endpoint list and field details are in Document 1 §7.

---

## 6. Checklist Before Sending an Import File

- [ ] File saved as `.csv`, UTF-8 without BOM
- [ ] Comma separator
- [ ] Header line present, no accents in headers
- [ ] `policy_number`, `product_code`, `last_name`, `first_name`, `phone` filled on every row
- [ ] Product codes taken from section 3.4
- [ ] Phone numbers as 8 digits, column formatted as Text
- [ ] Dates as `YYYY-MM-DD`; amounts without spaces
- [ ] Optional columns either filled on every row or removed
- [ ] Values containing commas enclosed in double quotes
- [ ] At most 5 000 rows per file
- [ ] Transferred through the channel agreed with SUNU IT

---

*End of Document 5.*
