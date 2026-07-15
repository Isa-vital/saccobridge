# SaccoBridge — API & Integration Specification

**Version:** 1.0 | **Date:** 2026-07-13 | **Status:** Approved

---

## 1. SMS Gateway — Pluggable Driver Architecture

### 1.1 Rationale
Relying on a single aggregator in Uganda is an operational risk. SaccoBridge uses Laravel's **Manager pattern**: build with Africa's Talking on day one, hot-swap to Yo!, Infobip, or others by changing one `.env` line.

### 1.2 Driver contract

```php
namespace App\Services\Sms\Contracts;

interface SmsDriver
{
    /** Send one message. Returns provider message id. Throws SmsException on failure. */
    public function send(SmsMessage $message): SmsResult;

    /** Remaining credit/balance with the provider, null if unsupported. */
    public function balance(): ?string;
}
```

```php
final class SmsMessage
{
    public function __construct(
        public readonly string $to,        // E.164, e.g. +2567XXXXXXXX
        public readonly string $body,      // ≤ 459 chars (3 segments) enforced
        public readonly ?string $senderId = null, // tenant's registered sender ID
    ) {}
}

final class SmsResult
{
    public function __construct(
        public readonly string $providerRef,
        public readonly string $status,    // sent|queued
        public readonly ?string $costRaw = null,
    ) {}
}
```

### 1.3 SmsManager

```php
namespace App\Services\Sms;

use Illuminate\Support\Manager;

class SmsManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->config->get('sms.driver', 'log'); // SMS_DRIVER env
    }

    public function createAfricastalkingDriver(): SmsDriver { /* ... */ }
    public function createLogDriver(): SmsDriver { /* dev/testing: writes to log */ }
    // future: createYoDriver(), createInfobipDriver()
}
```

- `config/sms.php`: `driver`, per-driver credentials, default sender ID.
- Per-tenant overrides (sender ID, API keys) read from tenant `settings` at send time.
- Custom notification channel `SmsChannel` bridges Laravel Notifications → `SmsManager`.
- All sends are **queued jobs**; results/failures written to `notification_logs`.

### 1.4 Africa's Talking driver
- Endpoint: `https://api.africastalking.com/version1/messaging` (POST, form-encoded).
- Auth: `apiKey` header + `username`. Env: `AT_USERNAME`, `AT_API_KEY`, `AT_SENDER_ID`.
- Phone normalization: Ugandan numbers `07XXXXXXXX` → `+2567XXXXXXXX` before send.
- Delivery reports: webhook `POST /webhooks/sms/africastalking` (central route, tenant resolved from message ref); updates `notification_logs.status`. Webhook validated by shared-secret path token.

### 1.5 Swap procedure
1. Implement `createXDriver()` returning an `SmsDriver`.
2. Add credentials block to `config/sms.php`.
3. Set `SMS_DRIVER=x` in `.env`. No other code changes.

### 1.6 Message templates (MVP)
| Template | Trigger | Example |
|----------|---------|---------|
| `deposit_alert` | Savings deposit posted | "SACCO: Deposit UGX 50,000 to A/C {no}. Bal: UGX {bal}. Ref {ref}" |
| `withdrawal_alert` | Withdrawal posted | similar |
| `loan_approved` | Loan approval | amount, term |
| `loan_disbursed` | Disbursement | amount, first due date |
| `installment_due` | X days before due (scheduled) | amount, date |
| `arrears_notice` | Classification job | overdue amount, days |
| `repayment_received` | Repayment posted | amount, outstanding |

---

## 2. Email
- Laravel `mail` channel, SMTP configurable per deployment (`MAIL_*` env); queued.
- Staff notices: pending approvals digest, teller day-end summary, failed-job alerts (ops).

---

## 3. Future Mobile / Third-Party API (design reserved, post-MVP)

### 3.1 Principles
- Same **service layer** — API controllers are thin wrappers over `LoanService`, `SavingsService`, etc. No ledger logic duplicated.
- Auth: **Laravel Sanctum** personal access tokens; abilities scoped (`member:read`, `teller:post`, ...).
- Tenancy: subdomain-based (`https://{sacco}.saccobridge.com/api/v1/...`), same middleware stack.
- Versioned prefix `api/v1`; JSON:API-ish envelopes `{ data, meta }`; errors `{ message, errors{} }` (Laravel standard).
- Rate limit 60/min/token; idempotency keys required on money-moving POSTs (`Idempotency-Key` header stored with the transaction reference).

### 3.2 Reserved surface (v1)
| Method & Path | Purpose | Ability |
|---------------|---------|---------|
| POST `/api/v1/auth/token` | Issue token (member/staff credentials + 2FA) | — |
| GET `/api/v1/me` | Profile | any |
| GET `/api/v1/savings-accounts` / `{id}/transactions` | Balances & statements | member:read |
| GET `/api/v1/loans` / `{id}` / `{id}/schedule` | Loan status & schedule | member:read |
| POST `/api/v1/loans/{id}/repayments` | Post repayment | teller:post |
| POST `/api/v1/savings-accounts/{id}/deposits` | Post deposit | teller:post |
| GET `/api/v1/reports/...` | Report data | reports:read |

### 3.3 Webhooks (outbound, post-MVP)
Tenant-configurable webhooks for `transaction.posted`, `loan.disbursed`, `loan.repaid` — HMAC-SHA256 signed (`X-SaccoBridge-Signature`), retried with backoff.

---

## 4. Mobile Money (roadmap placeholder)
MTN MoMo / Airtel Money collections & disbursements planned post-MVP behind the same driver philosophy (`PaymentManager` with provider drivers). Reconciliation via provider statements against GL clearing accounts.
