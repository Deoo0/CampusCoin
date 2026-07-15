<script setup lang="ts">
import { ref } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Clock, CheckCircle2, AlertCircle, XCircle } from 'lucide-vue-next'
import CampusCoinLayout from '../../layouts/CampusCoinLayout.vue'

defineOptions({ layout: CampusCoinLayout })

interface Order {
  id: string
  title: string
  amount_cc: number
  status: string
  created_at: string
}

const props = defineProps<{ orders: Order[] }>()

const completeForms = ref<Record<string, ReturnType<typeof useForm>>>({})
function completeFormFor(orderId: string) {
  if (!completeForms.value[orderId]) completeForms.value[orderId] = useForm({})
  return completeForms.value[orderId]
}
function markComplete(orderId: string) {
  completeFormFor(orderId).post(`/orders/${orderId}/complete`)
}

const statusIcon: Record<string, any> = {
  PENDING: Clock,
  DELIVERED: Clock,
  COMPLETED: CheckCircle2,
  DISPUTED: AlertCircle,
  CANCELLED: XCircle,
}

const statusColor: Record<string, string> = {
  PENDING: 'text-gold',
  DELIVERED: 'text-gold',
  COMPLETED: 'text-mint',
  DISPUTED: 'text-red-400',
  CANCELLED: 'text-ink-400',
}
</script>

<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-lg font-medium text-ink-50">My Orders</h1>
      <p class="text-xs text-ink-400 mt-0.5">Services you've ordered from other students</p>
    </div>

    <div class="space-y-2">
      <div v-for="order in props.orders" :key="order.id" class="bg-ink-950 border border-ink-700 rounded-xl p-4">
        <div class="flex items-center justify-between mb-2">
          <div class="text-sm text-ink-200">{{ order.title }}</div>
          <div class="flex items-center gap-1.5 font-mono text-xs" :class="statusColor[order.status] ?? 'text-ink-400'">
            <component :is="statusIcon[order.status] ?? Clock" class="w-3.5 h-3.5" stroke-width="2" />
            {{ order.status.toLowerCase() }}
          </div>
        </div>
        <div class="flex items-center justify-between">
          <div class="font-mono text-xs text-ink-400">{{ order.amount_cc.toFixed(2) }} CC · {{ order.created_at }}</div>
          <button
            v-if="order.status === 'PENDING'"
            class="bg-ink-800 border border-ink-700 rounded-lg px-3 py-1.5 text-xs text-mint disabled:opacity-50"
            :disabled="completeFormFor(order.id).processing"
            @click="markComplete(order.id)"
          >
            Mark complete
          </button>
        </div>
      </div>

      <p v-if="!props.orders.length" class="text-xs text-ink-400 text-center py-8">
        No orders yet — place one from the marketplace.
      </p>
    </div>
  </div>
</template>
