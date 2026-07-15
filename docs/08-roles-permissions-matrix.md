# SaccoBridge — Roles & Permissions Matrix

**Version:** 1.0 | **Date:** 2026-07-13 | **Status:** Approved

Legend: **C** create/initiate · **R** read · **U** update · **A** approve (checker) · **P** post (money-moving) · **X** execute/run · — none.
Super Admin operates only on the **central** app (tenants/plans) and has **no access to tenant financial data**.

## 1. Tenant Roles × Features

| Feature | SACCO Admin | Manager | Loan Officer | Teller | Accountant | Auditor | Member |
|---|---|---|---|---|---|---|---|
| **Members** |
| Register member | C R U | C R U | C R | R | R | R | own R |
| Approve/activate member | A | A | — | — | — | R | — |
| Exit member (settlement) | C | A | — | — | R | R | — |
| KYC documents | C R U | C R | C R | R | R | R | own R |
| Member register export | X | X | X | — | X | X | — |
| **Savings** |
| Savings products | C R U | R | R | R | R | R | — |
| Open/close account | C | A | — | C | R | R | own R |
| Deposit | — | — | — | P | — | R | own R |
| Withdrawal (below threshold) | — | — | — | P | — | R | own R |
| Withdrawal (above threshold) | A | A | — | C (initiate) | — | R | — |
| Transfers | — | A | — | C | — | R | own R |
| Teller session open/close | A | A | — | X (own) | R | R | — |
| Vault movements | A | A | — | C | R | R | — |
| Statements | X | X | X | X | X | X | own X |
| **Shares & Dividends** |
| Share products | C R U | R | — | R | R | R | — |
| Share purchase/redemption | — | A | — | P | R | R | own R |
| Declare dividend | C | A | — | — | C | R | — |
| Distribute dividend | — | A | — | — | P | R | own R |
| **Loans** |
| Loan products | C R U | R | R | R | R | R | — |
| Application (capture/appraise) | R | R | C U | — | R | R | own C R |
| Approve/reject loan | A | A | — | — | — | R | — |
| Disburse loan | — | A | — | P | P | R | own R |
| Repayment | — | — | — | P | P | R | own R |
| Guarantors/collateral | R | R | C U | — | R | R | own R |
| Restructure loan | — | A | C | — | R | R | — |
| Write-off | A | A (initiates go to Admin) | — | — | C | R | — |
| Payoff quote / statements | X | X | X | X | X | X | own X |
| **Accounting / GL** |
| Chart of accounts | C R U | R | — | — | C R U | R | — |
| Manual journal entry | — | A | — | — | C | R | — |
| Reverse journal entry | — | A | — | — | C | R | — |
| Trial balance / ledgers | R | R | — | — | R | R | — |
| Close financial period | A | A | — | — | X | R | — |
| Reconciliation | — | A | — | — | X | R | — |
| **Reports** |
| Financial statements | X | X | — | — | X | X | — |
| PAR / classification / provisioning | X | X | X (own portfolio) | — | X | X | — |
| UMRA return pack | X | X | — | — | X | X | — |
| Teller/day-end reports | X | X | — | X (own) | X | X | — |
| **Administration** |
| Staff users & role assignment | C R U | R | — | — | — | R | — |
| SACCO settings | C R U | R | — | — | R | R | — |
| Notification templates/log | C R U | R | — | — | R | R | — |
| Audit trail | R | R | — | — | R | R | — |
| Run demo seeder (non-prod) | X | — | — | — | — | — | — |

## 2. Maker-Checker Rules (enforced in services)
| Operation | Maker | Checker | Rule |
|-----------|-------|---------|------|
| Member activation | Any staff creator | SACCO Admin / Manager | checker ≠ maker |
| Withdrawal ≥ threshold | Teller | Manager / SACCO Admin | checker ≠ maker; threshold in settings |
| Loan approval | Loan Officer (submit) | Manager / SACCO Admin | approver ≠ officer; approved amount recorded |
| Loan restructure | Loan Officer | Manager | — |
| Write-off | Accountant | SACCO Admin + Manager | dual approval |
| Manual journal | Accountant | Manager | checker ≠ maker |
| Period close | Accountant | Manager / SACCO Admin | — |
| Dividend | Accountant/Admin declare | Manager approve | distribution only after approval |
| Vault movement | Teller/Accountant | Manager | — |

## 3. Hard Restrictions
- **Auditor**: read-only everywhere; any write permission assignment to auditor role is blocked by a guard test.
- **Member**: sees only own records; no staff routes.
- **Teller**: cannot approve anything; cannot post outside an open teller session.
- **No one** can edit or delete posted journal entries, activity log entries, or receipts — corrections are new (reversal) postings.
- Permission changes are themselves audit-logged and only SACCO Admin may make them.

## 4. Implementation Notes
- Permissions named `module.action` (e.g. `loans.approve`, `savings.deposit.post`, `gl.journal.create`, `reports.umra.view`); roles are permission bundles seeded by `RoleSeeder`.
- Laravel Policies back every controller action; Inertia shares the permission set for UI gating (server remains authoritative).
- Tests assert the full matrix: every role × representative route (allowed/denied) in `tests/Feature/Permissions`.
