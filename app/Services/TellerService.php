<?php

namespace App\Services;

use App\Models\GlAccount;
use App\Models\TellerSession;
use App\Models\User;
use App\Models\VaultMovement;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Teller cash management (FR-SAV-07): float issue, day close, reconciliation.
 * All cash movements post to the GL via TransactionService.
 */
class TellerService
{
    public function __construct(private readonly TransactionService $transactions)
    {
    }

    /**
     * Open a teller session by issuing float from the vault.
     * GL: Dr 1020 Teller Cash / Cr 1010 Cash in Vault.
     */
    public function openSession(User $teller, string $float, User $openedBy): TellerSession
    {
        if (TellerSession::openFor($teller) !== null) {
            throw new DomainException("{$teller->name} already has an open teller session.");
        }

        if (bccomp($float, '0.00', 2) !== 1) {
            throw new DomainException('Opening float must be greater than zero.');
        }

        return DB::transaction(function () use ($teller, $float, $openedBy) {
            $session = TellerSession::create([
                'user_id' => $teller->id,
                'opening_float' => $float,
                'status' => 'open',
                'opened_by' => $openedBy->id,
            ]);

            $entry = $this->transactions->post(
                description: "Float issue to teller {$teller->name} (session #{$session->id})",
                lines: [
                    ['account' => GlAccount::byCode('1020'), 'debit' => $float],
                    ['account' => GlAccount::byCode('1010'), 'credit' => $float],
                ],
                source: $session,
                postedBy: $openedBy,
            );

            VaultMovement::create([
                'direction' => 'vault_to_teller',
                'amount' => $float,
                'teller_session_id' => $session->id,
                'journal_entry_id' => $entry->id,
                'performed_by' => $openedBy->id,
            ]);

            return $session;
        });
    }

    /**
     * Close the session: teller declares counted cash, system computes the
     * expected amount and the variance, and the drawer returns to the vault.
     * GL: Dr 1010 Vault / Cr 1020 Teller Cash (system amount).
     */
    public function closeSession(TellerSession $session, string $declared, User $closedBy): TellerSession
    {
        if ($session->status !== 'open') {
            throw new DomainException('Session is not open.');
        }

        return DB::transaction(function () use ($session, $declared, $closedBy) {
            $expected = $session->expectedCash();
            $variance = bcsub($declared, $expected, 2);

            $session->update([
                'closing_declared' => $declared,
                'closing_system' => $expected,
                'variance' => $variance,
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            if (bccomp($expected, '0.00', 2) === 1) {
                $entry = $this->transactions->post(
                    description: "Teller day close — session #{$session->id} ({$session->teller->name})",
                    lines: [
                        ['account' => GlAccount::byCode('1010'), 'debit' => $expected],
                        ['account' => GlAccount::byCode('1020'), 'credit' => $expected],
                    ],
                    source: $session,
                    postedBy: $closedBy,
                );

                VaultMovement::create([
                    'direction' => 'teller_to_vault',
                    'amount' => $expected,
                    'teller_session_id' => $session->id,
                    'journal_entry_id' => $entry->id,
                    'performed_by' => $closedBy->id,
                ]);
            }

            return $session;
        });
    }

    /** Supervisor sign-off on a closed session (variance accepted/investigated). */
    public function reconcile(TellerSession $session, User $supervisor): TellerSession
    {
        if ($session->status !== 'closed') {
            throw new DomainException('Only closed sessions can be reconciled.');
        }

        if ($session->user_id === $supervisor->id) {
            throw new DomainException('A teller cannot reconcile their own session (maker-checker).');
        }

        $session->update(['status' => 'reconciled']);

        return $session;
    }
}
