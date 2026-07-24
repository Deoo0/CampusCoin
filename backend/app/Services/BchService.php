<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client for the standalone chain-service (native JS/CashScript,
 * see /chain-service). Laravel never touches CashScript or chipnet
 * directly — all of that logic lives in chain-service/index.mjs.
 */
class BchService
{
    private string $chainServiceBase;

    public function __construct()
    {
        $this->chainServiceBase = config('services.chain_service.base_url', 'http://localhost:3001');
    }

    public function generateDepositAddress(): string
    {
        return config('services.chipnet.deposit_address');
    }

    public function currentBchPhpRate(): float
    {
        // TODO: replace with a real BCH/PHP rate API (e.g. CoinGecko).
        return 14528.02;
    }

    public function verifyTxid(string $txid, string $expectedAddress, float $expectedBchAmount): ?array
    {
        $response = Http::timeout(10)
            ->withOptions(['verify' => ! app()->isLocal()])
            ->post("{$this->chainServiceBase}/verify-deposit", [
                'txid' => $txid,
                'expectedAddress' => $expectedAddress,
                'expectedBchAmount' => $expectedBchAmount,
            ]);

        if (! $response->ok() || ! $response->json('ok')) {
            return null;
        }

        return [
            'txid' => $txid,
            'amount' => $response->json('amount'),
            'confirmations' => $response->json('confirmations'),
        ];
    }

    public function sendPayout(string $toAddress, float $bchAmount): array
    {
        $amountSats = (int) round($bchAmount * 100_000_000);

        $response = Http::timeout(60)
            ->withOptions(['verify' => ! app()->isLocal()])
            ->post("{$this->chainServiceBase}/payout", [
                'toAddress' => $toAddress,
                'amountSats' => $amountSats,
            ]);

        if (! $response->ok() || ! $response->json('ok')) {
            throw new \RuntimeException('BCH payout failed: ' . ($response->json('error') ?? 'unknown error'));
        }

        return ['txid' => $response->json('txid')];
    }
}