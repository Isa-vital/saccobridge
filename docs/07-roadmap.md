# SaccoBridge — Project Roadmap

**Version:** 1.0 | **Date:** 2026-07-13 | **Status:** Approved

Phases are sequential unless marked parallel. Each phase ends with its acceptance criteria green and the demo seeder extended to cover the new module.

---

## Phase 0 — Scaffolding
**Deliverables**: Laravel 11 + Inertia + Vue 3 + Vite app; git repo; `.env.example`; Pest installed; base layout/theme; CI (lint + tests).
**Acceptance**: app boots at `saccobridge.test`; sample Inertia page renders; test suite runs.

## Phase 1 — Tenancy, Auth, RBAC, Audit (foundation)
**Deliverables**:
- `stancl/tenancy` v3: central + tenant DBs, subdomain identification, tenant migrations folder.
- Tenant provisioning flow (Super Admin creates tenant → DB created, migrated, seeded).
- Auth (sessions), TOTP 2FA for staff, `spatie/laravel-permission` with 8 seeded roles, `spatie/laravel-activitylog` wired to all models, settings store.
- Central admin UI (tenants list/create/suspend) + tenant login/dashboard shell.
**Acceptance**: two tenants provisioned; isolation test passes (A's data invisible to B); role-restricted routes verified; audit entries recorded for auth + CRUD.

## Phase 2 — Members & KYC
**Deliverables**: member CRUD + lifecycle (pending→active→dormant→exited), approval maker-checker, member numbering, next of kin, document uploads (tenant disk), member register with search/export, membership fee → GL (stub until Phase 3, then wired).
**Acceptance**: FR-MEM-01..09 demonstrable; register export works; activation requires approver ≠ creator.

## Phase 3 — Accounting / GL foundation *(blocks 4–7)*
**Deliverables**: CoA seeded (UMRA-aligned), `TransactionService` (balanced-post, reversal, period guard), financial periods + close, manual journals with approval, trial balance / ledger / journal views.
**Acceptance**: double-entry invariant test green; posting to closed period rejected; reversal flow works; trial balance balances.

## Phase 4 — Savings
**Deliverables**: savings products, accounts, deposits/withdrawals with gap-free receipts, threshold approvals, transfers, teller sessions + vault + day-end reconciliation, interest accrual job → GL, statements PDF, transaction SMS hooks (queued via Phase 8 channel, log driver until then).
**Acceptance**: FR-SAV-01..09; teller variance computed; interest run posts balanced entries; statement matches transactions.

## Phase 5 — Shares & Dividends *(parallel with 4 after 3)*
**Deliverables**: share products, purchase/redemption, share register, dividend declare→approve→distribute (pro-rata, to savings or cash) → GL.
**Acceptance**: FR-SHR-01..05; dividend total equals Σ allocations; GL posted.

## Phase 6 — Loans
**Deliverables**: loan products (flat + reducing balance), application→approval→disbursement workflow, guarantors & collateral, `AmortizationCalculator` + schedule generation, repayments with allocation order → GL, penalties, daily classification & provisioning job (UMRA buckets), restructuring, write-off with memo register, loan statements + payoff quote, cost-of-credit disclosure sheet.
**Acceptance**: FR-LNS-01..11; amortization unit tests match known schedules; classification job buckets seeded arrears loans correctly; provisions posted to GL.

## Phase 7 — UMRA Reports
**Deliverables**: Balance Sheet, Income Statement, PAR/aging + provisioning schedule, member register, savings summary, share register, loan reports (disbursement/collections/arrears/write-offs), UMRA return pack, capital adequacy & liquidity indicators; PDF (dompdf) + Excel (laravel-excel); heavy reports queued.
**Acceptance**: FR-RPT-01..09; every report reconciles to trial balance (automated reconciliation test).

## Phase 8 — Notifications *(parallel from Phase 4)*
**Deliverables**: `SmsManager` + Africa's Talking & log drivers, `SmsChannel`, templates (deposit/withdrawal/loan lifecycle/due reminders/arrears), email notices, `notification_logs`, delivery webhook, scheduled reminder jobs.
**Acceptance**: FR-NOT-01..05; driver swap via `.env` proven; failures logged and retried.

## Phase 9 — Hardening, Tests, Deploy
**Deliverables**: security pass per [05-security-policy.md](05-security-policy.md) (headers, rate limits, 2FA enforcement, Larastan, audits), full Pest suite green in CI, `spatie/laravel-backup` with offsite target + restore runbook, production provisioning (Ubuntu/Nginx/PHP-FPM/MySQL/Redis/Horizon/SSL wildcard), zero-downtime deploy script, monitoring/alerting.
**Acceptance**: OWASP checklist signed off; backup restore drill succeeds; staging tenant runs full demo flow end-to-end.

---

## Milestones
| Milestone | Definition |
|-----------|------------|
| M1 Foundation | Phases 0–1 complete (tenants provision + isolated) |
| M2 Core banking | Phases 2–4 (members save money, GL balanced) |
| M3 Full ledger | Phases 5–6 (shares, loans, classification) |
| M4 Compliance | Phase 7 (UMRA return pack reconciled) |
| M5 Launch-ready | Phases 8–9 (notifications, hardened, deployed) |

## Standing rules
- Demo seeder (`DemoSaccoSeeder`) grows with every phase — realistic data is a first-class deliverable.
- No phase closes with a failing double-entry invariant, isolation, or permission test.
- All financial logic in service classes; controllers stay thin (mobile API reuse).
