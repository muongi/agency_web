# SUNU Assurances Vie Burkina Faso — Project Documentation

**Mobile Premium Collection Solution on POS Terminals**

Documentation set produced for SUNU Assurances Vie Burkina Faso, answering the Terms of Reference of 12 August 2026 and the requirements e-mail of 21 August 2026 (meeting of 20 August 2026).

| # | Document | Answers | Markdown (EN) | Markdown (FR) | Word |
|---|---|---|---|---|---|
| 1 | Technical Architecture — overall and detailed, including the SUNULIFE integration design and four architecture diagrams | E-mail §1 and §2 | [EN](SUNU_01_TECHNICAL_ARCHITECTURE.md) | [FR](SUNU_01_ARCHITECTURE_TECHNIQUE_FR.md) | `SUNU_01_Technical_Architecture_EN.docx` · `SUNU_01_Architecture_Technique_FR.docx` |
| 2 | Security Architecture, Procedures and Controls | E-mail §2 | [EN](SUNU_02_SECURITY.md) | [FR](SUNU_02_SECURITE_FR.md) | `SUNU_02_Security_EN.docx` · `SUNU_02_Securite_FR.docx` |
| 3 | User Guide — all six profiles | E-mail §3 | [EN](SUNU_03_USER_GUIDE.md) | [FR](SUNU_03_GUIDE_UTILISATEUR_FR.md) | `SUNU_03_User_Guide_EN.docx` · `SUNU_03_Guide_Utilisateur_FR.docx` |
| 4 | Deployment Plan and Schedule | E-mail §5 and §6 | [EN](SUNU_04_DEPLOYMENT_PLAN.md) | [FR](SUNU_04_PLAN_DEPLOIEMENT_FR.md) | `SUNU_04_Deployment_Plan_EN.docx` · `SUNU_04_Plan_Deploiement_FR.docx` |
| 5 | Data Import and Exchange Formats — customer/policy CSV import spec, export and API formats, with sample CSVs in [samples/](samples/) | Follow-up request of 16 September 2026 | [EN](SUNU_05_DATA_IMPORT_FORMATS.md) | [FR](SUNU_05_FORMATS_IMPORT_DONNEES_FR.md) | `SUNU_05_Data_Import_Formats_EN.docx` · `SUNU_05_Formats_Import_Donnees_FR.docx` |

The markdown files are the source of truth. The `.docx` deliverables and the diagrams are generated from them.

## Diagrams

Four architecture diagrams live in [diagrams/](diagrams/), authored as SVG in both languages and rasterised to PNG for the Word documents.

| File | Shows |
|---|---|
| `01-system-context` | Who talks to what, and which dependencies are non-blocking |
| `02-component-map` | Reused / extended / new build across the POS app and back office |
| `03-sunulife-integration` | The SUNULIFE integration and its Phase 0 / 1 / 2 phasing |
| `04-deployment-topology` | Hosting topology and the trust boundary |

They are referenced from the markdown by a `<!-- figure: name | caption | replace -->` marker. `replace` means the figure supersedes the ASCII diagram that follows it, so the markdown stays readable on its own while the Word and web outputs get the drawn version.

## Regenerating

```bash
cd docs/sunu

# diagrams: SVG (both languages) then PNG for Word
python3 diagrams/build_diagrams.py      # English SVGs
python3 diagrams/translate_fr.py        # French SVGs (fails if a string is untranslated)
python3 diagrams/bounds_check.py        # catches labels that overflow their canvas
python3 diagrams/rasterize.py           # PNGs via headless Chrome, 3x

# Word deliverables, both languages
python3 build_docx.py                   # --lang en | fr | both (default both)
python3 verify_docx.py                  # checks every doc against its markdown source

# Doc 1 v1.1 (17 Sept 2026) added §14, the pilot as-built status. Keep it in step with
# /cust/RUNBOOKS/SUNU_TPE_BO_RUNBOOK.md when TPE or BO releases change the pilot.
```

```bash
# the web dossier (published as an Artifact)
cd web
python3 inject.py                       # inlines the figures + all 8 documents into template.html
python3 smoke.py                        # loads the page headless, asserts both languages render
```

`verify_docx.py` is the gate: it confirms that every table, code block, checkbox and figure in the source made it into the `.docx`, and that no raw markdown syntax leaked through. `smoke.py` is the equivalent gate for the web dossier: static string checks cannot tell whether the page's JavaScript actually ran, so it loads the page in headless Chrome in each language and asserts the status view and a document panel really rendered.

## Source requirements

- **TdR** — "Acquisition d'une solution digitale de collecte de primes via TPE", SUNU Central Operations Department, 12 August 2026. Four needs: subscription + first premium; premium collection by card with collector caps and pay-ins; tools (15 terminals, back office with profiles/permissions, 5 000 cards, personalisation device); APIs to update the core software.
- **E-mail of 21 August 2026** — six additional points: SUNULIFE API integration and synchronisation; technical architecture documentation; security documentation; user guide; physical card, virtual QR card and Mobile Money; initial deployment before end September 2026 and production launch by November 2026; management meeting on 1 October 2026.

## Key dates

| Date | Milestone |
|---|---|
| 30 September 2026 | Initial deployment (pilot) live — SUNU hard requirement |
| 1 October 2026 | Presentation to SUNU General Management |
| 28 October 2026 | Production go/no-go |
| Early November 2026 | Production launch — SUNU hard requirement |

## Reading order

- **SUNU management** — Doc 1 §2, §3 and §12.2 (open points); Doc 4 §2, §5 and §13.
- **SUNU IT** — Doc 1 in full; Doc 2 in full, particularly §12 (hardening programme) and §18 (go-live checklist); Doc 1 §8.5 is the questionnaire SUNU IT must route to the SUNULIFE editor.
- **SUNU Operations** — Doc 3; Doc 4 §6 (dated dependencies).
- **Delivery team** — all four.

## Open points requiring a SUNU decision

Ten open points are listed in Doc 1 §12.2 with impact and required-by dates, and restated as dated dependencies in Doc 4 §6. The three that decide the September date: hosting (5 Sept), card technology (8 Sept) and hardware ordering (8 Sept).

## Basis of the solution

The solution customises the existing agency-banking platform in this repository (Laravel back office) together with the Android POS application at `/cust/projects/ITS/its_telpo`. Doc 1 §4 states, component by component, what is reused as-is, what is extended and what is newly built.

## Language

Both languages are complete. English is written first and the French set is generated alongside it; the diagrams exist in both. Screenshots for the user guide are added after the UAT screen freeze (Doc 4 §9, deliverables L1–L4).
