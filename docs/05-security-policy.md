# SaccoBridge — Security Policy Document

**Version:** 1.0 | **Date:** 2026-07-13 | **Status:** Approved

---

## 1. OWASP Top 10 (2021) Controls Mapping

| Risk | Control in SaccoBridge |
|------|------------------------|
| A01 Broken Access Control | RBAC via `spatie/laravel-permission`; Laravel Policies on every model; route middleware per role; server-side checks only (UI hiding is cosmetic); tenant isolation via DB-per-tenant — no cross-tenant object references possible |
| A02 Cryptographic Failures | TLS 1.2+ everywhere (wildcard cert); bcrypt/argon2id password hashing; Laravel encrypted casts for sensitive columns (2FA secrets, SMS API keys); encrypted backups; disk encryption on VPS |
| A03 Injection | Eloquent/parameterized queries only; no raw SQL string concatenation; Form Request validation on every input; Vue auto-escaping (no `v-html` on user data) |
| A04 Insecure Design | Maker-checker on all sensitive ops; immutable ledger (reversal-only); threat-modeled tenancy boundary; numbered gap-free receipts |
| A05 Security Misconfiguration | `APP_DEBUG=false` in prod; security headers (CSP, X-Frame-Options DENY, X-Content-Type-Options, Referrer-Policy, HSTS); cookies `Secure`/`HttpOnly`/`SameSite=Lax`; directory listing off; least-privilege DB users |
| A06 Vulnerable Components | `composer audit` + `npm audit` in CI; Dependabot; pinned versions; minimal dependency surface |
| A07 Auth Failures | Mandatory TOTP 2FA for staff roles; rate-limited login (5/min) with lockout; strong password policy; session regeneration on login; idle session timeout (15 min for staff); no default credentials |
| A08 Software & Data Integrity | Signed deploys from git; queue payloads not user-modifiable; webhook signature verification (SMS delivery callbacks) |
| A09 Logging & Monitoring Failures | `spatie/laravel-activitylog` on all models + auth events (login, failed login, permission change); logs shipped off-box; Horizon monitoring; alerting on failed jobs & repeated auth failures |
| A10 SSRF | No user-supplied URLs fetched; outbound HTTP allow-listed to configured gateways only |

## 2. Authentication & Session
- **Staff/Members (web)**: Laravel session auth (Inertia). Staff require confirmed TOTP 2FA before accessing any tenant function.
- **Future mobile API**: Sanctum personal access tokens, scoped per ability; same service layer.
- Password reset via signed, expiring links; reset invalidates other sessions.

## 3. Authorization (RBAC)
- Roles/permissions per [08-roles-permissions-matrix.md](08-roles-permissions-matrix.md), seeded per tenant.
- **Maker-checker enforced in services** (not just UI): loan approval ≠ initiator; manual journal approval ≠ poster; large withdrawal approval ≠ teller.
- Auditor role is strictly read-only (enforced by permissions, verified by tests).

## 4. Tenant Isolation
- Database-per-tenant; default connection switched by tenancy middleware — a tenant request physically cannot query another tenant's DB.
- Per-tenant filesystem roots for KYC documents; signed temporary URLs for file access.
- Per-tenant cache prefixes and queue context.
- **Isolation test in CI**: seeded tenant A data must be unreachable from tenant B context.

## 5. Data Protection (Uganda DPPA 2019)
- Personal data (NIN, DOB, photos) collected only for KYC purpose; access restricted by role.
- Encrypted at rest: 2FA secrets, gateway credentials, backups; TLS in transit.
- Retention: financial records ≥ 10 years; no hard deletion of member financial history (deactivation instead).
- Member data export (subject access) supported via member statement/profile export.

## 6. Audit & Immutability
- Activity log: append-only; captures causer, subject, old/new values, IP, timestamp.
- Journal entries/lines immutable post-posting; corrections via reversal entries referencing the original.
- Financial period close locks postings; close/reopen events logged.

## 7. Rate Limiting & Abuse
- Login: 5/min per IP+email. General web: 60/min. Future API: 60/min per token. SMS-sending endpoints throttled.

## 8. Backup & Disaster Recovery
- `spatie/laravel-backup`: nightly per-tenant DB dumps + files, encrypted, to offsite object storage (S3-compatible).
- Retention: 7 daily, 4 weekly, 12 monthly, 10 yearly (aligned to retention rule).
- Quarterly restore drills; documented restore runbook. RPO ≤ 24h, RTO ≤ 4h (MVP targets).

## 9. Infrastructure Hardening (VPS)
- Ubuntu LTS, unattended security upgrades, UFW (22 restricted by IP, 80/443 only), SSH keys only (no password auth), fail2ban.
- Separate MySQL users: central app user; per-tenant provisioning user with limited grants; no root app access.
- Redis bound to localhost with auth; PHP-FPM per-pool user separation.

## 10. Incident Response (basics)
1. Detect (alerts: failed jobs, auth anomalies, error spikes) → 2. Contain (suspend tenant/user, rotate credentials) → 3. Assess via audit trail → 4. Notify affected SACCO and, where required, regulator/subjects per DPPA → 5. Post-mortem and control update.

## 11. Secure Development Practice
- CI: `composer audit`, `npm audit`, Pest test suite (including isolation, invariant, and permission tests), static analysis (Larastan level ≥ 6).
- Code review required for changes to `Services/Accounting`, tenancy config, and auth.
- Secrets only in `.env`/secret store; never committed. `.env.example` documents required keys without values.
