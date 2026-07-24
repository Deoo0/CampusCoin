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

        try {
            $result = $this->bch->sendPayout($withdrawal->bch_address, $bchAmount);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['withdrawal' => $e->getMessage()]);
        }

        $this->ledger->completeWithdrawal($withdrawal, $bchAmount, $result['txid']);

        return back()->with('success', "Withdrawal sent — txid {$result['txid']}");
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