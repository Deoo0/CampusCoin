<script setup lang="ts">
import { ref, nextTick, computed } from 'vue'
import { useForm } from '@inertiajs/vue3'
import QRCode from 'qrcode'
import QrScanner from 'qr-scanner'
import QrScannerWorkerPath from 'qr-scanner/qr-scanner-worker.min.js?url'
import {
  ArrowDownToLine, ArrowUpFromLine, ScanLine, Copy, Check,
  Clock, CheckCircle2, XCircle, Ban,
} from 'lucide-vue-next'
import CampusCoinLayout from '../../layouts/CampusCoinLayout.vue'

defineOptions({ layout: CampusCoinLayout })

QrScanner.WORKER_PATH = QrScannerWorkerPath

interface Deposit {
  id: string
  bch_address: string
  bch_amount: number | string
  cc_credited: number | string
  status: string
}

interface Withdrawal {
  id: string
  cc_amount: number | string
  bch_amount: number | string | null
  bch_address: string
  status: string
}

const props = defineProps<{
  ccBalance: number
  ccHeld: number
  recentDeposits: Deposit[]
  recentWithdrawals: Withdrawal[]
}>()

const statusIcon: Record<string, any> = {
  PENDING: Clock,
  PROCESSING: Clock,
  CONFIRMED: CheckCircle2,
  COMPLETED: CheckCircle2,
  FAILED: Ban,
  CANCELLED: XCircle,
}

const statusColor: Record<string, string> = {
  PENDING: 'text-gold',
  PROCESSING: 'text-gold',
  CONFIRMED: 'text-mint',
  COMPLETED: 'text-mint',
  FAILED: 'text-red-400',
  CANCELLED: 'text-ink-400',
}

// --- deposit ---
const depositForm = useForm({ php_amount: '' })
const showDepositForm = ref(false)

function requestDeposit() {
  depositForm.post('/wallet/deposit', {
    onSuccess: () => {
      depositForm.reset()
      showDepositForm.value = false
    },
  })
}

const confirmForms = ref<Record<string, ReturnType<typeof useForm>>>({})
function confirmFormFor(depositId: string) {
  if (!confirmForms.value[depositId]) confirmForms.value[depositId] = useForm({ txid: '' })
  return confirmForms.value[depositId]
}
function submitConfirm(depositId: string) {
  confirmFormFor(depositId).post(`/wallet/deposit/${depositId}/confirm`)
}

const depositCancelForms = ref<Record<string, ReturnType<typeof useForm>>>({})
function depositCancelFormFor(depositId: string) {
  if (!depositCancelForms.value[depositId]) depositCancelForms.value[depositId] = useForm({})
  return depositCancelForms.value[depositId]
}
function cancelDeposit(depositId: string) {
  depositCancelFormFor(depositId).post(`/wallet/deposit/${depositId}/cancel`)
}

function renderQr(el: HTMLCanvasElement | null, address: string) {
  if (!el) return
  QRCode.toCanvas(el, address, {
    width: 128,
    margin: 1,
    color: { dark: '#D7DEE8', light: '#00000000' },
  }).catch(() => {})
}

const copiedId = ref<string | null>(null)
async function copyAddress(depositId: string, address: string) {
  try {
    await navigator.clipboard.writeText(address)
    copiedId.value = depositId
    setTimeout(() => { if (copiedId.value === depositId) copiedId.value = null }, 1500)
  } catch {
    // non-secure context — address still selectable by hand
  }
}

// --- QR scanning ---
const scanningFor = ref<string | null>(null)
const videoEl = ref<HTMLVideoElement | null>(null)
let scanner: QrScanner | null = null

function extractTxid(scanned: string): string {
  const match = scanned.match(/[a-f0-9]{64}/i)
  return match ? match[0] : scanned.trim()
}

async function startScan(depositId: string) {
  scanningFor.value = depositId
  await nextTick()
  if (!videoEl.value) return
  scanner = new QrScanner(
    videoEl.value,
    (result) => {
      confirmFormFor(depositId).txid = extractTxid(result.data)
      stopScan()
    },
    { highlightScanRegion: true, highlightCodeOutline: true }
  )
  try {
    await scanner.start()
  } catch {
    stopScan()
  }
}

function stopScan() {
  scanner?.stop()
  scanner?.destroy()
  scanner = null
  scanningFor.value = null
}

// --- withdrawals ---
const showWithdrawForm = ref(false)
const withdrawForm = useForm({ cc_amount: '', bch_address: '' })

function requestWithdrawal() {
  withdrawForm.post('/withdrawals', {
    onSuccess: () => {
      withdrawForm.reset()
      showWithdrawForm.value = false
    },
  })
}

const withdrawalActionForms = ref<Record<string, ReturnType<typeof useForm>>>({})
function withdrawalActionFormFor(id: string) {
  if (!withdrawalActionForms.value[id]) withdrawalActionForms.value[id] = useForm({})
  return withdrawalActionForms.value[id]
}
function completeWithdrawal(id: string) {
  withdrawalActionFormFor(id).post(`/withdrawals/${id}/complete`)
}
function cancelWithdrawal(id: string) {
  withdrawalActionFormFor(id).post(`/withdrawals/${id}/cancel`)
}

const hasActivity = computed(() => props.recentDeposits.length || props.recentWithdrawals.length)
</script>

<template>
  <div class="space-y-5">
    <!-- Balance card -->
    <div class="bg-ink-950 border border-ink-700 rounded-2xl p-5">
      <div class="font-mono text-xs text-mint mb-1">&gt; cc_balance</div>
      <div class="flex items-baseline gap-2">
        <span class="font-mono text-4xl font-medium text-ink-50 tabular-nums">
          {{ props.ccBalance.toFixed(2) }}
        </span>
        <span class="font-mono text-sm text-ink-400">CC</span>
      </div>

      <div class="flex gap-6 mt-4 pt-3 border-t border-ink-700">
        <div>
          <div class="text-xs text-ink-400 mb-1">spendable</div>
          <div class="font-mono text-sm text-ink-200 tabular-nums">{{ props.ccBalance.toFixed(2) }}</div>
        </div>
        <div>
          <div class="text-xs text-ink-400 mb-1">held</div>
          <div class="font-mono text-sm text-gold tabular-nums">{{ props.ccHeld.toFixed(2) }}</div>
        </div>
        <div>
          <div class="text-xs text-ink-400 mb-1">≈ PHP</div>
          <div class="font-mono text-sm text-ink-200 tabular-nums">₱{{ props.ccBalance.toFixed(0) }}</div>
        </div>
      </div>
    </div>

    <!-- Actions -->
    <div class="grid grid-cols-2 gap-3">
      <button
        class="flex items-center justify-center gap-2 bg-ink-800 border border-ink-700 rounded-xl py-3.5 text-sm text-ink-200 active:scale-[0.98] transition-transform"
        @click="showDepositForm = !showDepositForm; showWithdrawForm = false"
      >
        <ArrowDownToLine class="w-4 h-4 text-mint" stroke-width="2" />
        Deposit
      </button>
      <button
        class="flex items-center justify-center gap-2 bg-ink-800 border border-ink-700 rounded-xl py-3.5 text-sm text-ink-200 active:scale-[0.98] transition-transform"
        @click="showWithdrawForm = !showWithdrawForm; showDepositForm = false"
      >
        <ArrowUpFromLine class="w-4 h-4 text-gold" stroke-width="2" />
        Withdraw
      </button>
    </div>

    <!-- Deposit form -->
    <div v-if="showDepositForm" class="bg-ink-950 border border-ink-700 rounded-2xl p-4">
      <label class="text-xs text-ink-400 block mb-2">PHP amount to load</label>
      <div class="flex gap-2">
        <input
          v-model="depositForm.php_amount"
          type="number"
          inputmode="decimal"
          min="1"
          step="0.01"
          placeholder="100"
          class="flex-1 bg-ink-900 border border-ink-700 rounded-lg px-3 py-2.5 text-sm text-ink-200 font-mono focus:outline-none focus:border-mint"
        />
        <button
          class="bg-mint-dim text-mint text-sm font-medium px-5 rounded-lg disabled:opacity-50"
          :disabled="depositForm.processing"
          @click="requestDeposit"
        >
          Generate
        </button>
      </div>
      <p v-if="depositForm.errors.php_amount" class="text-xs text-red-400 mt-2">{{ depositForm.errors.php_amount }}</p>
    </div>

    <!-- Withdraw form -->
    <div v-if="showWithdrawForm" class="bg-ink-950 border border-ink-700 rounded-2xl p-4 space-y-3">
      <div>
        <label class="text-xs text-ink-400 block mb-2">CC amount to withdraw</label>
        <input
          v-model="withdrawForm.cc_amount"
          type="number"
          inputmode="decimal"
          min="0.01"
          step="0.01"
          placeholder="50"
          class="w-full bg-ink-900 border border-ink-700 rounded-lg px-3 py-2.5 text-sm text-ink-200 font-mono focus:outline-none focus:border-mint"
        />
        <p v-if="withdrawForm.errors.cc_amount" class="text-xs text-red-400 mt-1">{{ withdrawForm.errors.cc_amount }}</p>
      </div>
      <div>
        <label class="text-xs text-ink-400 block mb-2">Your chipnet BCH address</label>
        <input
          v-model="withdrawForm.bch_address"
          type="text"
          placeholder="bchtest:q..."
          class="w-full bg-ink-900 border border-ink-700 rounded-lg px-3 py-2.5 text-sm text-ink-200 font-mono focus:outline-none focus:border-mint"
        />
        <p v-if="withdrawForm.errors.bch_address" class="text-xs text-red-400 mt-1">{{ withdrawForm.errors.bch_address }}</p>
      </div>
      <button
        class="w-full bg-mint-dim text-mint text-sm font-medium py-2.5 rounded-lg disabled:opacity-50"
        :disabled="withdrawForm.processing"
        @click="requestWithdrawal"
      >
        Request withdrawal
      </button>
    </div>

    <p v-if="!hasActivity" class="text-xs text-ink-400 text-center py-8">
      No activity yet — deposit some CC to get started.
    </p>

    <!-- Deposits -->
    <div v-if="props.recentDeposits.length">
      <div class="text-xs text-ink-400 mb-2 lowercase tracking-wide">deposits</div>
      <div class="space-y-2">
        <div v-for="d in props.recentDeposits" :key="d.id" class="bg-ink-950 border border-ink-700 rounded-xl p-3.5">
          <div class="flex items-center justify-between mb-1.5">
            <div class="font-mono text-xs text-ink-400">{{ d.bch_address.slice(0, 18) }}…</div>
            <div class="flex items-center gap-1.5 font-mono text-xs" :class="statusColor[d.status] ?? 'text-ink-400'">
              <component :is="statusIcon[d.status]" class="w-3.5 h-3.5" stroke-width="2" />
              {{ d.status.toLowerCase() }}
            </div>
          </div>
          <div class="font-mono text-xs text-ink-400 mb-2">
            {{ d.bch_amount }} BCH → {{ d.cc_credited }} CC
          </div>

          <div v-if="d.status === 'PENDING'">
            <div class="flex items-start gap-3 mb-3 bg-ink-900 border border-ink-700 rounded-lg p-3">
              <canvas :ref="(el) => renderQr(el as HTMLCanvasElement, d.bch_address)" class="rounded shrink-0" />
              <div class="min-w-0 flex-1">
                <div class="text-xs text-ink-400 mb-1">send exactly {{ d.bch_amount }} BCH to</div>
                <div class="font-mono text-xs text-ink-200 break-all mb-2">{{ d.bch_address }}</div>
                <button
                  class="flex items-center gap-1.5 font-mono text-xs px-2 py-1 rounded border border-ink-700"
                  :class="copiedId === d.id ? 'text-mint border-mint' : 'text-ink-400'"
                  @click="copyAddress(d.id, d.bch_address)"
                >
                  <component :is="copiedId === d.id ? Check : Copy" class="w-3 h-3" stroke-width="2" />
                  {{ copiedId === d.id ? 'copied' : 'copy address' }}
                </button>
              </div>
            </div>

            <div v-if="scanningFor === d.id" class="mb-3 bg-ink-900 border border-ink-700 rounded-lg p-2">
              <video ref="videoEl" class="w-full rounded-lg" playsinline></video>
              <button class="mt-2 w-full font-mono text-xs text-red-400 border border-ink-700 rounded py-2" @click="stopScan">
                Stop scanning
              </button>
            </div>

            <div class="flex flex-wrap gap-2">
              <input
                v-model="confirmFormFor(d.id).txid"
                type="text"
                placeholder="paste txid, or scan"
                class="flex-1 min-w-[140px] bg-ink-900 border border-ink-700 rounded px-2 py-2 text-xs text-ink-200 font-mono focus:outline-none focus:border-mint"
              />
              <button class="flex items-center gap-1 bg-ink-800 border border-ink-700 rounded px-3 text-xs text-ink-200" @click="startScan(d.id)">
                <ScanLine class="w-3.5 h-3.5" stroke-width="2" /> Scan
              </button>
              <button
                class="bg-ink-800 border border-ink-700 rounded px-3 py-2 text-xs text-mint disabled:opacity-50"
                :disabled="confirmFormFor(d.id).processing"
                @click="submitConfirm(d.id)"
              >
                Confirm
              </button>
              <button
                class="bg-ink-800 border border-ink-700 rounded px-3 py-2 text-xs text-red-400 disabled:opacity-50"
                :disabled="depositCancelFormFor(d.id).processing"
                @click="cancelDeposit(d.id)"
              >
                Cancel
              </button>
            </div>
            <p v-if="confirmFormFor(d.id).errors.txid" class="text-xs text-red-400 mt-1">{{ confirmFormFor(d.id).errors.txid }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Withdrawals -->
    <div v-if="props.recentWithdrawals.length">
      <div class="text-xs text-ink-400 mb-2 lowercase tracking-wide">withdrawals</div>
      <div class="space-y-2">
        <div v-for="w in props.recentWithdrawals" :key="w.id" class="bg-ink-950 border border-ink-700 rounded-xl p-3.5">
          <div class="flex items-center justify-between mb-1.5">
            <div class="font-mono text-xs text-ink-200">{{ w.cc_amount }} CC</div>
            <div class="flex items-center gap-1.5 font-mono text-xs" :class="statusColor[w.status] ?? 'text-ink-400'">
              <component :is="statusIcon[w.status]" class="w-3.5 h-3.5" stroke-width="2" />
              {{ w.status.toLowerCase() }}
            </div>
          </div>
          <div class="font-mono text-xs text-ink-400 mb-2 break-all">→ {{ w.bch_address.slice(0, 24) }}…</div>

          <div v-if="w.status === 'PENDING'" class="flex gap-2">
            <button
              class="flex-1 bg-ink-800 border border-ink-700 rounded px-3 py-2 text-xs text-mint disabled:opacity-50"
              :disabled="withdrawalActionFormFor(w.id).processing"
              @click="completeWithdrawal(w.id)"
            >
              Mark sent
            </button>
            <button
              class="flex-1 bg-ink-800 border border-ink-700 rounded px-3 py-2 text-xs text-red-400 disabled:opacity-50"
              :disabled="withdrawalActionFormFor(w.id).processing"
              @click="cancelWithdrawal(w.id)"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
