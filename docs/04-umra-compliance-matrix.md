# SaccoBridge — UMRA Tier 4 Compliance Matrix

**Version:** 1.0 | **Date:** 2026-07-13 | **Status:** Approved

Basis: **Tier 4 Microfinance Institutions and Money Lenders Act, 2016** (Uganda) and UMRA regulations/guidelines for SACCOs. Provisioning rates and thresholds below are configurable in system settings so they can track current UMRA schedules without code changes.

Status legend: ✅ designed-in (this document set) | 🔨 built when the mapped phase lands | Phases per [07-roadmap.md](07-roadmap.md).

| # | UMRA / Act Requirement | System Feature | Module / Requirement ID | Phase | Status |
|---|------------------------|----------------|-------------------------|-------|--------|
| 1 | Maintain an accurate **register of members** | Statutory member register, searchable, exportable | FR-MEM-08 | 2 | 🔨 |
| 2 | Member identification (**KYC**) — ID, photo, contacts | NIN, photo, signature, documents, next of kin | FR-MEM-01..03 | 2 | 🔨 |
| 3 | Proper **books of account** | Double-entry GL; all operations post journals | FR-GL-01..07 | 3 | 🔨 |
| 4 | Records of **savings/deposits** per member | Savings accounts, transactions with receipts, statements | FR-SAV-02..08 | 4 | 🔨 |
| 5 | Records of **share capital** per member | Share register, share transactions, GL-linked | FR-SHR-01..03 | 5 | 🔨 |
| 6 | Records of **loans** — terms, security, repayment | Loan file: application, approval, schedule, guarantors, collateral, repayments | FR-LNS-01..10 | 6 | 🔨 |
| 7 | **Loan classification** by aging | Daily job: performing / watch (1–30) / substandard (31–60) / doubtful (61–90) / loss (>90) | FR-LNS-08 | 6 | 🔨 |
| 8 | **Provisioning** for bad and doubtful debts | Auto provision at 1% / 5% / 25% / 50% / 100% (configurable), posted to GL | FR-LNS-08, FR-RPT-03 | 6 | 🔨 |
| 9 | **Portfolio at Risk** monitoring | PAR report with aging buckets | FR-RPT-03 | 7 | 🔨 |
| 10 | Periodic **financial statements** | Balance Sheet, Income Statement from trial balance | FR-RPT-01..02 | 7 | 🔨 |
| 11 | **Periodic returns to UMRA** | UMRA return pack (quarterly/annual), PDF + Excel | FR-RPT-06, FR-RPT-08 | 7 | 🔨 |
| 12 | **Capital adequacy** monitoring | Core capital ratio indicator on reports dashboard | FR-RPT-07 | 7 | 🔨 |
| 13 | **Liquidity** monitoring | Liquid assets ratio indicator | FR-RPT-07 | 7 | 🔨 |
| 14 | **Consumer protection**: disclosure of interest, fees, total cost of credit | Loan offer sheet showing rates, all fees, effective cost before acceptance | FR-LNS-11 | 6 | 🔨 |
| 15 | Interest rate transparency — no hidden charges | All charges defined on product; charge log per loan | FR-LNS-01, loan_charges | 6 | 🔨 |
| 16 | **Data/record retention** (≥ 10 years) | No deletion of financial records; immutable ledger; archived tenant backups | NFR-04, NFR-09 | 3, 9 | ✅ |
| 17 | **Audit trail** of transactions and changes | Append-only activity log + immutable journal, reversal-only corrections | FR-ADM-03 | 1 | 🔨 |
| 18 | Internal controls — **segregation of duties** | RBAC + maker-checker (teller posts / manager approves; officer submits / manager approves loans; accountant posts / manager approves manual journals) | FR-ADM-02, matrix doc | 1+ | 🔨 |
| 19 | Board/committee **loan approval** governance | Approval workflow with recorded approver, date, amount | FR-LNS-04 | 6 | 🔨 |
| 20 | Safe custody of member data (**confidentiality**, Data Protection & Privacy Act 2019) | DB-per-tenant isolation, encryption in transit/at rest, access control | NFR-01..02, security doc | 1, 9 | ✅ |
| 21 | Receipts for member transactions | Numbered, gap-free receipts on every teller transaction | FR-SAV-03 | 4 | 🔨 |
| 22 | Dormant account identification | Dormancy rules per product; dormancy job; dormant register | FR-SAV-01, FR-MEM-05 | 4 | 🔨 |
| 23 | Write-off governance | Write-off requires approval; memo register of written-off loans | FR-LNS-09 | 6 | 🔨 |
| 24 | Dividend declaration governance | Declare → approve → distribute workflow, GL-posted | FR-SHR-04..05 | 5 | 🔨 |
| 25 | Business continuity of records | Nightly automated per-tenant backups, offsite, restore-tested | NFR-05 | 9 | 🔨 |

## Configurable compliance parameters (Settings)
| Parameter | Default | Note |
|-----------|---------|------|
| Classification buckets (days) | 0 / 1–30 / 31–60 / 61–90 / >90 | Match current UMRA schedule |
| Provisioning rates | 1% / 5% / 25% / 50% / 100% | Editable if UMRA revises |
| Large-withdrawal approval threshold | UGX 1,000,000 | Per-tenant |
| Record retention | 10 years | Minimum |
| Financial year start | 1 January | Per-tenant |

## Out of scope (MVP) — noted for roadmap
- Direct electronic submission to UMRA portal (manual export of return pack instead).
- AML/CFT sanction-list screening (planned post-MVP).
- Credit Reference Bureau integration (post-MVP).
