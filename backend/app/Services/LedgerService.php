<?php

namespace App\Services;

use App\Models\CcTransaction;
use App\Models\Order;
use App\Models\ServiceListing;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use Illuminate\Support\Facades\DB;

/**
 * All CC balance mutations go through here. Never touch
 * wallet.cc_balance / cc_held directly from a controller.
 */
class LedgerService
{
    public function creditDeposit(Wallet $wallet, float $amount, string $depositId): void
    {
        DB::transaction(function () use ($wallet, $amount, $depositId) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();
            $locked->increment('cc_balance', $amount);

            CcTransaction::create([
                'to_wallet_id' => $wallet->id,
                'amount' => $amount,
                'type' => 'DEPOSIT_CREDIT',
                'reference_id' => $depositId,
                'reference_type' => 'bch_deposit',
            ]);
        });
    }

    public function holdForOrder(Wallet $buyerWallet, ServiceListing $listing): Order
    {
        return DB::transaction(function () use ($buyerWallet, $listing) {
            $locked = Wallet::whereKey($buyerWallet->id)->lockForUpdate()->first();

            if ($locked->cc_balance < $listing->price_cc) {
                throw new \RuntimeException('Insufficient CC balance.');
            }

            $locked->decrement('cc_balance', $listing->price_cc);
            $locked->increment('cc_held', $listing->price_cc);

            $order = Order::create([
                'buyer_id' => $locked->user_id,
                'listing_id' => $listing->id,
                'amount_cc' => $listing->price_cc,
                'status' => 'PENDING',
            ]);

            CcTransaction::create([
                'from_wallet_id' => $locked->id,
                'amount' => $listing->price_cc,
                'type' => 'ORDER_PAYMENT',
                'reference_id' => $order->id,
                'reference_type' => 'order',
            ]);

            return $order;
        });
    }

    public function releaseOnCompletion(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $buyerWallet = Wallet::where('user_id', $order->buyer_id)->lockForUpdate()->first();
            $sellerWallet = Wallet::where('user_id', $order->listing->seller_id)->lockForUpdate()->first();

            $buyerWallet->decrement('cc_held', $order->amount_cc);
            $sellerWallet->increment('cc_balance', $order->amount_cc);

            $order->update(['status' => 'COMPLETED', 'completed_at' => now()]);

            CcTransaction::create([
                'from_wallet_id' => $buyerWallet->id,
                'to_wallet_id' => $sellerWallet->id,
                'amount' => $order->amount_cc,
                'type' => 'ORDER_RELEASE',
                'reference_id' => $order->id,
                'reference_type' => 'order',
            ]);
        });
    }

    /**
     * Requesting a withdrawal locks the CC immediately — moved from
     * cc_balance to cc_held, same "no double-spend" pattern as order
     * holds — so the same CC can't also be spent in the marketplace
     * while a withdrawal is pending.
     */
    public function holdForWithdrawal(Wallet $wallet, float $ccAmount, string $bchAddress): WithdrawalRequest
    {
        return DB::transaction(function () use ($wallet, $ccAmount, $bchAddress) {
            $locked = Wallet::whereKey($wallet->id)->lockForUpdate()->first();

            if ($locked->cc_balance < $ccAmount) {
                throw new \RuntimeException('Insufficient CC balance.');
            }

            $locked->decrement('cc_balance', $ccAmount);
            $locked->increment('cc_held', $ccAmount);

            return WithdrawalRequest::create([
                'wallet_id' => $locked->id,
                'cc_amount' => $ccAmount,
                'bch_address' => $bchAddress,
                'status' => 'PENDING',
            ]);
        });
    }

    /**
     * Finalizes a withdrawal — CC actually leaves the system here (the
     * held amount is removed from cc_held, not moved anywhere else,
     * since the corresponding BCH has gone out on-chain). bchAmount is
     * the actual BCH sent, computed by the caller from a live rate at
     * send time.
     */
    public function completeWithdrawal(WithdrawalRequest $withdrawal, float $bchAmount, string $txid): void
    {
        DB::transaction(function () use ($withdrawal, $bchAmount, $txid) {
            $wallet = Wallet::whereKey($withdrawal->wallet_id)->lockForUpdate()->first();
            $wallet->decrement('cc_held', $withdrawal->cc_amount);

            $withdrawal->update([
                'status' => 'COMPLETED',
                'bch_amount' => $bchAmount,
                'txid' => $txid,
                'processed_at' => now(),
            ]);

            CcTransaction::create([
                'from_wallet_id' => $wallet->id,
                'amount' => $withdrawal->cc_amount,
                'type' => 'WITHDRAWAL',
                'reference_id' => $withdrawal->id,
                'reference_type' => 'withdrawal_request',
            ]);
        });
    }

    /**
     * Only a still-PENDING withdrawal can be cancelled — releases the
     * held CC back to spendable balance.
     */
    public function cancelWithdrawal(WithdrawalRequest $withdrawal): void
    {
        DB::transaction(function () use ($withdrawal) {
            $wallet = Wallet::whereKey($withdrawal->wallet_id)->lockForUpdate()->first();
            $wallet->decrement('cc_held', $withdrawal->cc_amount);
            $wallet->increment('cc_balance', $withdrawal->cc_amount);

            $withdrawal->update(['status' => 'FAILED']);
        });
    }
}