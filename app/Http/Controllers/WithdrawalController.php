<?php

namespace App\Http\Controllers;

use App\Models\WithdrawalRequest;
use App\Services\BchService;
use App\Services\LedgerService;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function __construct(
        private LedgerService $ledger,
        private BchService $bch,
    ) {}

    public function store(Request $request)
    {
        $data = $request->validate([
            'cc_amount' => 'required|numeric|min:0.01',
            'bch_address' => 'required|string',
        ]);

        try {
            $this->ledger->holdForWithdrawal($request->user()->wallet, $data['cc_amount'], $data['bch_address']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['cc_amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Withdrawal requested — CC held until processed.');
    }

    /**
     * Stands in for the real "admin/cron batches pending withdrawals and
     * actually sends BCH via Paytaca" step from the design doc. For this
     * prototype, hitting this button IS that step — it doesn't verify
     * BCH actually left a wallet anywhere, it just finalizes the ledger
     * side as if it did. Fine for a demo; a real batch-processing job is
     * still a TODO before this touches real money at scale.
     */
    public function complete(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->wallet_id !== $request->user()->wallet->id) {
            abort(403);
        }

        if ($withdrawal->status !== 'PENDING') {
            return back()->withErrors(['withdrawal' => 'Only a pending withdrawal can be completed.']);
        }

        $rate = $this->bch->currentBchPhpRate();
        $bchAmount = round($withdrawal->cc_amount / $rate, 8);

        $this->ledger->completeWithdrawal($withdrawal, $bchAmount);

        return back()->with('success', "Withdrawal completed — {$bchAmount} BCH marked sent.");
    }

    public function cancel(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->wallet_id !== $request->user()->wallet->id) {
            abort(403);
        }

        if ($withdrawal->status !== 'PENDING') {
            return back()->withErrors(['withdrawal' => 'Only a pending withdrawal can be cancelled.']);
        }

        $this->ledger->cancelWithdrawal($withdrawal);

        return back()->with('success', 'Withdrawal cancelled — CC released back to your balance.');
    }
}