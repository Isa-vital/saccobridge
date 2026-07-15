# SaccoBridge — Software Requirements Specification (SRS)

**Version:** 1.0 | **Date:** 2026-07-13 | **Status:** Approved

---

## 1. Introduction

### 1.1 Purpose
SaccoBridge is a multi-tenant SaaS platform for managing Savings and Credit Cooperative Organizations (SACCOs) in Uganda, designed to comply with the **UMRA (Uganda Microfinance Regulatory Authority) Tier 4 Microfinance Institutions and Money Lenders Act, 2016** and associated regulations.

### 1.2 Scope
The system covers member management & KYC, savings, shares & dividends, loans, double-entry accounting, UMRA regulatory reporting, role-based access control, audit trails, and SMS/email notifications. Each SACCO (tenant) operates on its own isolated database, accessed via a dedicated subdomain.

### 1.3 Definitions
| Term | Meaning |
|------|---------|
| SACCO | Savings and Credit Cooperative Organization |
| UMRA | Uganda Microfinance Regulatory Authority |
| Tenant | One SACCO instance on the platform |
| PAR | Portfolio at Risk |
| CoA | Chart of Accounts |
| GL | General Ledger |
| KYC | Know Your Customer |
| NIN | National Identification Number (Uganda) |
| Maker-Checker | Two-person control: one initiates, another approves |

### 1.4 System Context
- **Platform (central)**: `saccobridge.com` — tenant registry, subscriptions, platform administration.
- **Tenant**: `{sacco}.saccobridge.com` — the SACCO's own management system on its own database.

---

## 2. Actors / User Roles

| # | Role | Scope | Summary |
|---|------|-------|---------|
| 1 | Super Admin | Central | Platform owner; manages tenants, plans, billing |
| 2 | SACCO Admin | Tenant | Full control of one SACCO; staff, settings, products |
| 3 | Manager | Tenant | Approvals (loans, large withdrawals), oversight, reports |
| 4 | Loan Officer | Tenant | Loan applications, appraisal, guarantors, collections |
| 5 | Teller | Tenant | Cash transactions: deposits, withdrawals, repayments |
| 6 | Accountant | Tenant | GL, journals, reconciliation, period close, reports |
| 7 | Auditor | Tenant | Read-only access to everything + audit trail |
| 8 | Member | Tenant | Self-service portal: balances, statements, loan status |

See [08-roles-permissions-matrix.md](08-roles-permissions-matrix.md) for the full access grid.

---

## 3. Functional Requirements

### 3.1 Member Management & KYC (FR-MEM)
- **FR-MEM-01**: Register members with: full name, NIN, date of birth, gender, phone(s), email, physical address (district/subcounty/village), occupation, passport photo, signature specimen.
- **FR-MEM-02**: Capture at least one next of kin (name, relationship, phone, NIN optional).
- **FR-MEM-03**: Upload and store KYC documents (national ID copy, proof of address) per member.
- **FR-MEM-04**: Auto-generate sequential member numbers with configurable prefix (e.g. `SB-00001`).
- **FR-MEM-05**: Member lifecycle: `pending → active → dormant → exited`; approval required to activate (maker-checker).
- **FR-MEM-06**: Membership fee charged on activation, posted to GL.
- **FR-MEM-07**: Support member exit with settlement of savings/shares and clearance of loans.
- **FR-MEM-08**: Searchable member register (by name, member no, NIN, phone) — the statutory member register per UMRA.
- **FR-MEM-09**: Support institutional/group members in addition to individuals.

### 3.2 Savings (FR-SAV)
- **FR-SAV-01**: Define savings products: name, code, interest rate, interest calculation basis (simple/compound, daily/monthly balance), minimum balance, minimum opening deposit, withdrawal limits/fees, dormancy rules.
- **FR-SAV-02**: Open savings accounts for active members; one member may hold multiple accounts across products.
- **FR-SAV-03**: Teller deposits and withdrawals with receipt generation; every transaction posts balanced GL entries.
- **FR-SAV-04**: Withdrawals above a configurable threshold require Manager approval (maker-checker).
- **FR-SAV-05**: Account-to-account transfers within the SACCO.
- **FR-SAV-06**: Interest accrual (scheduled job) and posting to accounts + GL.
- **FR-SAV-07**: Teller cash management: teller float assignment, vault, end-of-day teller reconciliation.
- **FR-SAV-08**: Member statements (per account, date range) exportable to PDF.
- **FR-SAV-09**: Prevent withdrawal below minimum balance or against uncleared blocks (e.g., savings pledged against a loan).

### 3.3 Shares & Dividends (FR-SHR)
- **FR-SHR-01**: Define share products: nominal value per share, minimum/maximum shares per member.
- **FR-SHR-02**: Share purchase and (rule-governed) share transfer/redemption transactions, posted to GL.
- **FR-SHR-03**: Share register per member and totals for the SACCO.
- **FR-SHR-04**: Dividend declaration (rate or amount, for a financial year) with Manager/Admin approval.
- **FR-SHR-05**: Dividend distribution pro-rata to shareholding; option to pay to savings account; posted to GL.

### 3.4 Loans (FR-LNS)
- **FR-LNS-01**: Define loan products: name, code, interest rate & method (**flat** or **reducing balance**), term limits, amount limits, grace period, fees (application, processing — fixed or %), penalty rate for arrears, required guarantors, savings-multiple rule (e.g., loan ≤ 3× savings).
- **FR-LNS-02**: Loan application workflow: `draft → submitted → under_review → approved/rejected → disbursed → active → closed/written_off`.
- **FR-LNS-03**: Capture guarantors (members guaranteeing with savings/shares) and collateral items (description, value, documents).
- **FR-LNS-04**: Approval is maker-checker: Loan Officer submits, Manager (or committee) approves. Approval records approver, date, and approved amount/term (may differ from applied).
- **FR-LNS-05**: On disbursement, generate a full amortization schedule (per product interest method) and post disbursement to GL. Disbursement to savings account or cash.
- **FR-LNS-06**: Repayments (cash/from savings) allocate in configurable order (default: penalties → fees → interest → principal), update the schedule, and post to GL.
- **FR-LNS-07**: Automatic penalty computation on overdue installments.
- **FR-LNS-08**: Loan classification per UMRA aging: **Performing (0 days), Watch (1–30), Substandard (31–60), Doubtful (61–90), Loss (>90)** with provisioning rates 1% / 5% / 25% / 50% / 100% respectively (configurable to match current UMRA schedule).
- **FR-LNS-09**: Loan restructuring/rescheduling with approval and audit trail; written-off loans tracked in a memo register.
- **FR-LNS-10**: Loan statements and payoff quote (early settlement) generation.
- **FR-LNS-11**: Disclose total cost of credit (interest, fees, effective rate) on the loan offer — consumer protection requirement.

### 3.5 Accounting / General Ledger (FR-GL)
- **FR-GL-01**: Seeded, editable Chart of Accounts (assets, liabilities, equity, income, expenses) aligned to UMRA reporting lines.
- **FR-GL-02**: All financial operations post via a single `TransactionService` producing **balanced double-entry journal entries** (Σ debits = Σ credits enforced at write time).
- **FR-GL-03**: Manual journal entries by Accountant with Manager approval (maker-checker).
- **FR-GL-04**: Trial balance, general ledger, and journal listing views with date filters.
- **FR-GL-05**: Financial periods (monthly) with period close by Accountant; no postings into closed periods.
- **FR-GL-06**: Every journal entry links to its source (loan, savings transaction, etc.) for drill-down.
- **FR-GL-07**: Bank/cash reconciliation support.

### 3.6 Reports (FR-RPT)
- **FR-RPT-01**: Balance Sheet (Statement of Financial Position).
- **FR-RPT-02**: Income Statement (Statement of Comprehensive Income).
- **FR-RPT-03**: Portfolio report: PAR analysis, aging buckets, classification & provisioning schedule.
- **FR-RPT-04**: Member register (statutory), savings summary, share register, dividend schedule.
- **FR-RPT-05**: Loan reports: disbursements, collections, arrears, write-offs, by officer/product.
- **FR-RPT-06**: UMRA periodic return pack (quarterly/annual) assembling required figures from the GL.
- **FR-RPT-07**: Capital adequacy and liquidity indicators (core capital ratio, liquid assets ratio).
- **FR-RPT-08**: All reports exportable to **PDF and Excel**; figures must reconcile to the trial balance.
- **FR-RPT-09**: Teller/day-end reports: cash position, transaction listing per teller.

### 3.7 Notifications (FR-NOT)
- **FR-NOT-01**: SMS via pluggable driver (Manager pattern); default driver **Africa's Talking**; swappable via `.env`. See [06-api-integration-spec.md](06-api-integration-spec.md).
- **FR-NOT-02**: Transaction alerts (deposit, withdrawal, repayment received) to member phone.
- **FR-NOT-03**: Loan lifecycle notifications: approval, disbursement, installment due reminder (configurable days before), arrears notice.
- **FR-NOT-04**: Email notifications where member email exists; system emails for staff (approvals pending, day-end).
- **FR-NOT-05**: All notifications queued (Horizon); delivery status logged.

### 3.8 Administration, RBAC & Audit (FR-ADM)
- **FR-ADM-01**: Central: Super Admin creates/suspends tenants; tenant provisioning creates database, runs migrations, seeds CoA and default roles.
- **FR-ADM-02**: Tenant: SACCO Admin manages staff users, assigns roles; roles/permissions via `spatie/laravel-permission`.
- **FR-ADM-03**: Full audit trail (`spatie/laravel-activitylog`): who did what, when, old/new values, IP — immutable to all tenant roles (read-only for Auditor).
- **FR-ADM-04**: 2FA (TOTP) mandatory for staff roles; optional for members.
- **FR-ADM-05**: SACCO settings: branding/logo, financial year, currency (UGX), approval thresholds, numbering formats.
- **FR-ADM-06**: Demo seeder produces a complete demo SACCO (members, accounts, loans with schedules, GL postings) for testing.

---

## 4. Non-Functional Requirements

| ID | Requirement |
|----|-------------|
| NFR-01 | **Security**: OWASP Top 10 controls; see [05-security-policy.md](05-security-policy.md) |
| NFR-02 | **Tenant isolation**: database-per-tenant; no query may cross tenant boundaries |
| NFR-03 | **Integrity**: monetary values `DECIMAL(20,2)`; rates `DECIMAL(9,6)`; all financial writes in DB transactions; double-entry invariant enforced |
| NFR-04 | **Auditability**: every state-changing action logged; journal entries immutable (corrections by reversal only) |
| NFR-05 | **Availability**: target 99.5% uptime; daily automated backups per tenant with tested restore |
| NFR-06 | **Performance**: p95 page response < 500 ms at 100 concurrent users per tenant; reports for 50k members < 30 s (queued) |
| NFR-07 | **Localization**: currency UGX, timezone Africa/Kampala, date format d/m/Y; English UI |
| NFR-08 | **Scalability**: horizontal scaling of app tier; tenant DBs distributable across DB servers |
| NFR-09 | **Compliance**: UMRA Tier 4 requirements mapped in [04-umra-compliance-matrix.md](04-umra-compliance-matrix.md); data retention ≥ 10 years |
| NFR-10 | **Usability**: responsive UI usable on low-bandwidth connections; SPA experience via Inertia |

---

## 5. Constraints
- Stack: Laravel 11+, Inertia.js, Vue 3, Vite, MySQL 8.
- Tenancy: `stancl/tenancy` v3, database-per-tenant, subdomain identification.
- Financial logic must live in service classes independent of HTTP layer (future mobile API reuse).
- Deployment: cloud VPS (Ubuntu, Nginx, PHP-FPM, MySQL, Redis, Supervisor/Horizon).

## 6. Assumptions
- Each SACCO operates a single currency (UGX).
- Internet connectivity available at SACCO offices (cloud-hosted system).
- SMS costs borne per tenant; gateway credentials configurable per tenant.

## 7. Traceability
Requirements map to phases in [07-roadmap.md](07-roadmap.md) and to UMRA obligations in [04-umra-compliance-matrix.md](04-umra-compliance-matrix.md).
