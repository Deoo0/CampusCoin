import { encodePrivateKeyWif, secp256k1 } from '@bitauth/libauth'
import { randomBytes } from 'crypto'

function generateValidPrivateKey() {
  let key
  do {
    key = randomBytes(32)
  } while (!secp256k1.validatePrivateKey(key))
  return key
}

const privateKey = generateValidPrivateKey()
const wif = encodePrivateKeyWif(privateKey, 'testnet')

console.log(JSON.stringify({ wif }, null, 2))