// Run ONCE, manually, to stand up the vault:
//   node scripts/vault-setup.mjs <operator-wif> <max-withdrawal-sats>
//
// Prints the vault's covenant address and operator pubkey — both go into
// this service's .env (CHIPNET_DEPOSIT_ADDRESS, CHIPNET_OPERATOR_PUBKEY).
// index.mjs recompiles the SAME contract from those saved .env values on
// every request — if they don't match exactly what you used here, that's
// a DIFFERENT contract address with no funds in it.
import { compileFile } from 'cashc'
import { Contract, ElectrumNetworkProvider } from 'cashscript'
import { decodePrivateKeyWif, secp256k1 } from '@bitauth/libauth'
import { dirname, resolve } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))

async function main() {
  const [, , wif, maxWithdrawalSats] = process.argv

  const decoded = decodePrivateKeyWif(wif)
  if (typeof decoded === 'string') throw new Error(decoded)

  const pubkey = secp256k1.derivePublicKeyCompressed(decoded.privateKey)
  const operatorPubkeyHex = Buffer.from(pubkey).toString('hex')

  const artifact = compileFile(resolve(__dirname, '../contracts/CampusVault.cash'))
  const provider = new ElectrumNetworkProvider('chipnet')
  const contract = new Contract(
    artifact,
    [operatorPubkeyHex, BigInt(maxWithdrawalSats)],
    { provider, addressType: 'p2sh32' }
  )

  console.log(JSON.stringify({
    vaultAddress: contract.address,
    operatorPubkeyHex,
    maxWithdrawalSats,
  }, null, 2))
}

main()
