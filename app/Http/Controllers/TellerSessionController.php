<?php

namespace App\Http\Controllers;

use App\Models\TellerSession;
use App\Models\User;
use App\Services\TellerService;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TellerSessionController extends Controller
{
    public function __construct(private readonly TellerService $tellers)
    {
    }

    public function index(Request $request): Response
    {
        $sessions = TellerSession::query()
            ->with(['teller:id,name', 'opener:id,name'])
            ->orderByDesc('id')
            ->paginate(15)
            ->through(fn (TellerSession $session) => [
                'id' => $session->id,
                'teller' => $session->teller->name,
                'opening_float' => $session->opening_float,
                'closing_declared' => $session->closing_declared,
                'closing_system' => $session->closing_system,
                'variance' => $session->variance,
                'status' => $session->status,
                'opened_by' => $session->opener->name,
                'opened_at' => $session->created_at->format('Y-m-d H:i'),
                'closed_at' => $session->closed_at?->format('Y-m-d H:i'),
                'is_own' => $session->user_id === $request->user()->id,
            ]);

        $tellers = User::role(['teller', 'sacco-admin'])->get(['id', 'name']);

        return Inertia::render('savings/Sessions', [
            'sessions' => $sessions,
            'tellers' => $tellers,
            'can' => [
                'manage' => $request->user()->can('savings.approve'), // manager/admin
            ],
        ]);
    }

    public function open(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('savings.approve'), 403);

        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'opening_float' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $session = $this->tellers->openSession(
                User::findOrFail($data['user_id']),
                number_format((float) $data['opening_float'], 2, '.', ''),
                $request->user(),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Session #{$session->id} opened with float {$session->opening_float}.");
    }

    public function close(Request $request, TellerSession $session): RedirectResponse
    {
        // The teller closes their own session; managers may close any
        if ($session->user_id !== $request->user()->id && ! $request->user()->can('savings.approve')) {
            abort(403);
        }

        $data = $request->validate([
            'closing_declared' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $session = $this->tellers->closeSession(
                $session,
                number_format((float) $data['closing_declared'], 2, '.', ''),
                $request->user(),
            );
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = "Session #{$session->id} closed.";
        if (bccomp((string) $session->variance, '0.00', 2) !== 0) {
            $message .= " VARIANCE: {$session->variance} — requires investigation.";
        }

        return back()->with(bccomp((string) $session->variance, '0.00', 2) === 0 ? 'success' : 'error', $message);
    }

    public function reconcile(Request $request, TellerSession $session): RedirectResponse
    {
        abort_unless($request->user()->can('savings.approve'), 403);

        try {
            $this->tellers->reconcile($session, $request->user());
        } catch (DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Session #{$session->id} reconciled.");
    }
}
