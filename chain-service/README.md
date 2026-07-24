# CampusCoin chain service

All CashScript + chipnet logic lives here as native JS/Node, the same
pattern the Paytaca refresher class taught (compileFile, Contract,
ElectrumNetworkProvider, TransactionBuilder). Laravel never touches
CashScript directly — it just calls this over HTTP.

## Setup

    npm install
    cp .env.example .env

Pick a spend cap (sats), then run once:

    node scripts/vault-setup.mjs <your-chipnet-wif> 100000

Copy the printed `vaultAddress` and `operatorPubkeyHex` into `.env`
(`CHIPNET_OPERATOR_PUBKEY`), and put `vaultAddress` into the *Laravel*
app's `.env` as `CHIPNET_DEPOSIT_ADDRESS` — that's where students'
deposits should be sent.

Send a small amount of chipnet BCH to that vault address so there's
something to test a payout against.

## Run

    npm start

Runs on `http://localhost:3001`. Test it directly before wiring Laravel
to it:

    curl http://localhost:3001/health
    curl http://localhost:3001/vault-info

## Endpoints

- `POST /verify-deposit` — `{ txid, expectedAddress, expectedBchAmount }` → `{ ok, amount, confirmations }`
- `POST /payout` — `{ toAddress, amountSats }` → `{ ok, txid }`
- `GET /vault-info` — `{ vaultAddress }`

Field-name accuracy note: `/verify-deposit`'s parsing of the chipnet
explorer's response is written defensively (checks a couple of likely
shapes) but hasn't been confirmed against a live response from this
environment. It logs the raw response to the console on every call —
if a real deposit doesn't verify, check that log line first.
