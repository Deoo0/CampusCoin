// CampusCoin's chain service — everything CashScript/chipnet-related
// lives here, as native JavaScript, the same way the refresher class
// taught it. Laravel calls this over plain HTTP; it never touches
// CashScript directly.
import "dotenv/config";
import express from "express";
import { compileFile } from "cashc";
import {
  Contract,
  ElectrumNetworkProvider,
  TransactionBuilder,
  SignatureTemplate,
} from "cashscript";
import { decodeCashAddress } from "@bitauth/libauth";
import { dirname, resolve } from "path";
import { fileURLToPath } from "url";

const __dirname = dirname(fileURLToPath(import.meta.url));
const app = express();
app.use(express.json());

const PORT = process.env.PORT || 3001;
const EXPLORER_BASE = "https://chipnet.bchexplorer.info";

const artifact = compileFile(resolve(__dirname, "contracts/CampusVault.cash"));
const provider = new ElectrumNetworkProvider("chipnet");

function getVaultContract() {
  return new Contract(
    artifact,
    [
      process.env.CHIPNET_OPERATOR_PUBKEY,
      BigInt(process.env.CHIPNET_MAX_WITHDRAWAL_SATS),
    ],
    { provider, addressType: "p2sh32" },
  );
}

app.get("/health", (req, res) => res.json({ ok: true }));

// Mirrors what BchService::verifyTxid used to do directly in PHP — now
// the real implementation, in the same language/ecosystem the explorer
// call and the payout logic both live in.
app.post("/verify-deposit", async (req, res) => {
  const { txid, expectedAddress, expectedBchAmount } = req.body;

  if (!/^[a-f0-9]{64}$/i.test(txid || "")) {
    return res.json({ ok: false, error: "Malformed txid." });
  }

  try {
    const response = await fetch(`${EXPLORER_BASE}/api/bch/tx/${txid}`);
    const data = await response.json();

    if (!response.ok) {
      return res.json({
        ok: false,
        error: `Explorer returned ${response.status}`,
      });
    }

    // Field names below are a best guess at the explorer's response
    // shape, same caveat as before — not verified against a live call
    // from this environment. Log the raw body so a real test run makes
    // any mismatch obvious immediately.
    console.log("chipnet tx lookup", JSON.stringify(data));

    const outputs = data.vout ?? data.outputs ?? [];
    let receivedSats = 0;

    for (const out of outputs) {
      const outAddress =
        out?.scriptPubKey?.addresses?.[0] ?? out?.address ?? null;
      if (outAddress !== expectedAddress) continue;

      if (out.satoshis !== undefined) {
        receivedSats += Number(out.satoshis);
      } else if (out.value !== undefined) {
        receivedSats += Math.round(Number(out.value) * 100_000_000);
      }
    }

    const receivedBch = receivedSats / 100_000_000;

    if (receivedBch <= 0 || receivedBch < expectedBchAmount * 0.99) {
      return res.json({
        ok: false,
        error: "TXID does not pay the expected address/amount.",
      });
    }

    return res.json({
      ok: true,
      amount: receivedBch,
      confirmations: data.confirmations ?? 0,
    });
  } catch (err) {
    return res.json({ ok: false, error: err.message });
  }
});

// Spends from the vault covenant, enforcing the on-chain spend cap.
app.post("/payout", async (req, res) => {
  const { toAddress, amountSats } = req.body;
  console.log("payout requested", { toAddress, amountSats });

  try {
    const contract = getVaultContract();
    const sig = new SignatureTemplate(process.env.CHIPNET_PAYOUT_WIF);
    const amount = BigInt(amountSats);

    const decoded = decodeCashAddress(toAddress);
    if (typeof decoded === "string") throw new Error(decoded);
    const recipientPkh = Buffer.from(decoded.payload).toString("hex");

    const utxos = await contract.getUtxos();
    if (!utxos.length) throw new Error("Vault has no UTXOs to spend.");

    const totalIn = utxos.reduce((sum, u) => sum + u.satoshis, 0n);
    const fee = 1000n;

    if (totalIn < amount + fee) {
      throw new Error(
        `Vault balance (${totalIn} sats) can't cover this withdrawal (${amount} sats) plus the network fee (${fee} sats). Fund the vault with more chipnet BCH.`,
      );
    }

    const builder = new TransactionBuilder({ provider });
    utxos.forEach((utxo) => {
      builder.addInput(
        utxo,
        contract.unlock.withdraw(sig, recipientPkh, amount),
      );
    });

    builder.addOutput({ to: toAddress, amount });

    const change = totalIn - amount - fee;
    if (change >= 546n) {
      builder.addOutput({ to: contract.address, amount: change });
    }

    const tx = await builder.send();
    console.log("payout sent — txid:", tx.txid);
    return res.json({ ok: true, txid: tx.txid });
  } catch (err) {
    return res.json({ ok: false, error: err.message });
  }
});

app.get("/vault-info", async (req, res) => {
  const contract = getVaultContract();
  const balanceSats = await contract.getBalance();
  res.json({
    vaultAddress: contract.address,
    balanceSats: balanceSats.toString(),
    balanceBch: (Number(balanceSats) / 100_000_000).toFixed(8),
  });
});

app.listen(PORT, () => console.log(`chain-service listening on :${PORT}`));
