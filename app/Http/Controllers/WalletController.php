<?php

namespace App\Http\Controllers;

use App\Models\BchDeposit;
use App\Services\BchService;
use App\Services\LedgerService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WalletController extends Controller
{
    public function __construct(
        private BchService $bch,
        private LedgerService $ledger,
    ) {}

    public function index(Request $request)
    {
        $wallet = $request->user()->wallet
            ?? $request->user()->wallet()->create(['cc_balance' => 0, 'cc_held' => 0]);

        return Inertia::render('Wallet/Index', [
            'ccBalance' => (float) $wallet->cc_balance,
            'ccHeld' => (float) $wallet->cc_held,
            'recentDeposits' => $wallet->deposits()->latest()->take(10)->get(),
            'recentWithdrawals' => $wallet->withdrawalRequests()->latest()->take(10)->get(),
        ]);
    }

    public function deposit(Request $request)
    {
        $data = $request->validate([
            'php_amount' => 'required|numeric|min:1',
        ]);

        $wallet = $request->user()->wallet
            ?? $request->user()->wallet()->create(['cc_balance' => 0, 'cc_held' => 0]);

        $rate = $this->bch->currentBchPhpRate();
        $bchAmount = round($data['php_amount'] / $rate, 8);

        $deposit = BchDeposit::create([
            'wallet_id' => $wallet->id,
            'bch_address' => $this->bch->generateDepositAddress(),
            'bch_amount' => $bchAmount,
            'php_rate_at_deposit' => $rate,
            'cc_credited' => $data['php_amount'], // 1 CC = ₱1, locked at this moment
            'status' => 'PENDING',
        ]);

        return back()->with('success', "Deposit address generated: {$deposit->bch_address} — send {$bchAmount} BCH");
    }

    public function confirmDeposit(Request $request, BchDeposit $deposit)
    {
        if ($deposit->wallet_id !== $request->user()->wallet->id) {
            abort(403);
        }

        if ($deposit->status === 'CONFIRMED') {
            return back()->with('success', 'This deposit was already confirmed.');
        }

        $data = $request->validate(['txid' => 'required|string']);

        $verified = $this->bch->verifyTxid($data['txid'], $deposit->bch_address, (float) $deposit->bch_amount);

        if (! $verified) {
            return back()->withErrors(['txid' => 'Could not verify that transaction ID.']);
        }

        $deposit->update(['txid' => $verified['txid'], 'status' => 'CONFIRMED']);

        $this->ledger->creditDeposit($deposit->wallet, (float) $deposit->cc_credited, $deposit->id);

        return back()->with('success', "Deposit confirmed — {$deposit->cc_credited} CC credited.");
    }

    public function cancelDeposit(Request $request, BchDeposit $deposit)
    {
        if ($deposit->wallet_id !== $request->user()->wallet->id) {
            abort(403);
        }

        if ($deposit->status !== 'PENDING') {
            return back()->withErrors(['deposit' => 'Only a pending deposit can be cancelled.']);
        }

        // Status flips to CANCELLED — the row stays in bch_deposits either
        // way, so the record of the attempt (address, amount, timestamps)
        // is preserved for the audit trail, it just never gets credited.
        $deposit->update(['status' => 'CANCELLED']);

        return back()->with('success', 'Deposit cancelled.');
    }
}