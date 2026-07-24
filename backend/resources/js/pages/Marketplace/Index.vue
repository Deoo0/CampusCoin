<script setup lang="ts">
import { useForm } from '@inertiajs/vue3'
import { FileText, Printer, Code2, Palette, MoreHorizontal } from 'lucide-vue-next'
import CampusCoinLayout from '../../layouts/CampusCoinLayout.vue'

defineOptions({ layout: CampusCoinLayout })

interface Listing {
  id: string
  title: string
  category: string
  price_cc: number
}

const props = defineProps<{ listings: Listing[] }>()

const orderForm = useForm({ listing_id: '' })

function placeOrder(listingId: string) {
  orderForm.listing_id = listingId
  orderForm.post('/orders')
}

const categoryIcon: Record<string, any> = {
  EDITING: FileText,
  PRINTING: Printer,
  CODING: Code2,
  DESIGN: Palette,
  OTHER: MoreHorizontal,
}

const categoryColor: Record<string, string> = {
  EDITING: 'text-ink-200 bg-ink-800',
  PRINTING: 'text-gold bg-gold-dim/20',
  CODING: 'text-mint bg-mint-dim/20',
  DESIGN: 'text-ink-200 bg-ink-800',
  OTHER: 'text-ink-400 bg-ink-800',
}
</script>

<template>
  <div class="space-y-4">
    <div>
      <h1 class="text-lg font-medium text-ink-50">Marketplace</h1>
      <p class="text-xs text-ink-400 mt-0.5">Campus services, priced in CC</p>
    </div>

    <div class="space-y-2">
      <div
        v-for="listing in props.listings"
        :key="listing.id"
        class="bg-ink-950 border border-ink-700 rounded-xl p-4 flex items-center gap-3"
      >
        <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0" :class="categoryColor[listing.category]">
          <component :is="categoryIcon[listing.category] ?? MoreHorizontal" class="w-5 h-5" stroke-width="2" />
        </div>

        <div class="min-w-0 flex-1">
          <div class="text-sm text-ink-200 truncate">{{ listing.title }}</div>
          <div class="font-mono text-xs text-ink-400">{{ listing.category.toLowerCase() }}</div>
        </div>

        <div class="text-right shrink-0">
          <div class="font-mono text-sm text-mint mb-1.5">{{ listing.price_cc }} CC</div>
          <button
            class="bg-ink-800 border border-ink-700 rounded-lg px-3 py-1.5 text-xs text-ink-200 disabled:opacity-50"
            :disabled="orderForm.processing"
            @click="placeOrder(listing.id)"
          >
            Order
          </button>
        </div>
      </div>

      <p v-if="!props.listings.length" class="text-xs text-ink-400 text-center py-8">
        No active listings yet.
      </p>
    </div>
  </div>
</template>
