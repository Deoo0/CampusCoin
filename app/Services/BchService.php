<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wraps all outbound BCH network calls, using Paytaca's own chipnet
 * explorer (https://chipnet.bchexplorer.info) since that's the network
 * the Paytaca wallet transacts on. Docs: github.com/paytaca/bitcoincash-explorer
 *
 * NOTE: verifyTxid()'s response parsing is written defensively (checking
 * a couple of plausible field name shapes) because it hasn't been tested
 * against a live response yet. First real TXID you confirm with — check
 * storage/logs/laravel.log for the "chipnet tx lookup" entry it writes,
 * and if verification fails unexpectedly, send me that logged raw
 * response and I'll fix the field mapping.
 */
class BchService
{
    private string $explorerBase = 'https://chipnet.bchexplorer.info';

    public function generateDepositAddress(): string
    {
        // TODO: real HD wallet derivation, one fresh address per deposit.
        // Simplest path for a hackathon: use a single fixed chipnet
        // address you control (e.g. from your own Paytaca wallet) and
        // rely on the TXID + amount check below rather than per-deposit
        // addresses. Swap this to real derivation if you need to
        // distinguish which student a given payment came from by address.
        return config('services.chipnet.deposit_address');
    }

    public function currentBchPhpRate(): float
    {
        // TODO: replace with a real BCH/PHP rate API (e.g. CoinGecko).
        // Placeholder rate so deposit math has something to work with.
        return 14528.02; // 1 BCH ≈ ₱32,000 — adjust or fetch live
    }

    /**
     * Looks up a TXID on Paytaca's chipnet explorer and checks whether it
     * pays the expected address at least the expected amount.
     *
     * Returns ['txid' => ..., 'amount' => float BCH received, 'confirmations' => int]
     * on success, or null if the TXID doesn't exist or doesn't pay the
     * expected address/amount.
     */
    public function verifyTxid(string $txid, string $expectedAddress, float $expectedBchAmount): ?array
    {
        if (! preg_match('/^[a-f0-9]{64}$/i', $txid)) {
            return null;
        }

        $response = Http::timeout(10)
        ->withOptions(['verify' => ! app()->isLocal()])
        ->get("{$this->explorerBase}/api/bch/tx/{$txid}");

        Log::info('chipnet tx lookup', ['txid' => $txid, 'status' => $response->status(), 'body' => $response->body()]);

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();

        // Outputs may come back under "vout" or "outputs" depending on
        // the exact response shape — check both.
        $outputs = $data['vout'] ?? $data['outputs'] ?? [];

        $receivedSatoshis = 0;
        foreach ($outputs as $out) {
            $outAddress = $out['scriptPubKey']['addresses'][0]
                ?? $out['address']
                ?? null;

            if ($outAddress === $expectedAddress) {
                // value may be in BCH (float) or satoshis (int) depending
                // on the field — "value" is typically BCH, "satoshis" is sats.
                if (isset($out['satoshis'])) {
                    $receivedSatoshis += (int) $out['satoshis'];
                } elseif (isset($out['value'])) {
                    $receivedSatoshis += (int) round(((float) $out['value']) * 100_000_000);
                }
            }
        }

        $receivedBch = $receivedSatoshis / 100_000_000;

        if ($receivedBch <= 0 || $receivedBch < $expectedBchAmount * 0.99) {
            // allow 1% slack for fee-adjusted sends
            return null;
        }

        return [
            'txid' => $txid,
            'amount' => $receivedBch,
            'confirmations' => (int) ($data['confirmations'] ?? 0),
        ];
    }
}
