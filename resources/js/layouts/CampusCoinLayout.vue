<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3'
import { Wallet, Store, ListChecks, LogOut, CheckCircle2, XCircle } from 'lucide-vue-next'
import { computed } from 'vue'

const page = usePage()

const navItems = [
  { href: '/wallet', label: 'Wallet', icon: Wallet },
  { href: '/marketplace', label: 'Market', icon: Store },
  { href: '/orders', label: 'Orders', icon: ListChecks },
]

function isActive(href: string) {
  return page.url === href || page.url.startsWith(href + '/')
}

// Reads Laravel's flash session data. Requires 'flash' to be shared from
// HandleInertiaRequests middleware — see the note in SETUP.md if this
// stays empty even after a successful action.
const flashSuccess = computed(() => (page.props.flash as any)?.success as string | undefined)
const flashErrors = computed(() => Object.values((page.props.errors as any) ?? {}) as string[])
</script>

<template>
  <div class="min-h-screen bg-ink-900 text-ink-200 lg:flex">
    <!-- Desktop sidebar -->
    <aside class="hidden lg:flex lg:flex-col lg:w-64 lg:shrink-0 border-r border-ink-700 bg-ink-950 px-4 py-6">
      <div class="flex items-center gap-2 mb-10 px-2">
        <span class="w-2 h-2 rounded-full bg-mint"></span>
        <span class="font-mono text-base font-medium text-ink-50">campuscoin_</span>
      </div>
      <nav class="flex flex-col gap-1">
        <Link
          v-for="item in navItems"
          :key="item.href"
          :href="item.href"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
          :class="isActive(item.href) ? 'bg-ink-800 text-ink-50' : 'text-ink-400 hover:text-ink-200 hover:bg-ink-800/50'"
        >
          <component :is="item.icon" class="w-4 h-4" stroke-width="2" />
          {{ item.label }}
        </Link>
      </nav>
      <div class="mt-auto px-2 space-y-3">
        <span class="font-mono text-xs text-ink-400 bg-ink-800 border border-ink-700 rounded px-2 py-1 inline-block">chipnet</span>
        <Link
          href="/logout"
          method="post"
          as="button"
          class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-ink-400 hover:text-red-400 w-full transition-colors"
        >
          <LogOut class="w-4 h-4" stroke-width="2" /> Log out
        </Link>
      </div>
    </aside>

    <!-- Mobile top bar -->
    <header class="lg:hidden sticky top-0 z-20 flex items-center justify-between px-4 py-3 bg-ink-950/95 backdrop-blur border-b border-ink-700">
      <div class="flex items-center gap-2">
        <span class="w-2 h-2 rounded-full bg-mint"></span>
        <span class="font-mono text-sm font-medium text-ink-50">campuscoin_</span>
      </div>
      <span class="font-mono text-xs text-ink-400 bg-ink-800 border border-ink-700 rounded px-2 py-1">chipnet</span>
    </header>

    <div class="flex-1 flex flex-col min-h-screen">
      <!-- Flash messages -->
      <div v-if="flashSuccess || flashErrors.length" class="max-w-md lg:max-w-2xl w-full mx-auto px-4 pt-4">
        <div
          v-if="flashSuccess"
          class="flex items-center gap-2 bg-mint-dim/20 border border-mint-dim text-mint text-sm rounded-lg px-3 py-2.5 mb-2"
        >
          <CheckCircle2 class="w-4 h-4 shrink-0" stroke-width="2" />
          <span class="break-words">{{ flashSuccess }}</span>
        </div>
        <div
          v-for="(err, i) in flashErrors"
          :key="i"
          class="flex items-center gap-2 bg-red-950/40 border border-red-900 text-red-400 text-sm rounded-lg px-3 py-2.5 mb-2"
        >
          <XCircle class="w-4 h-4 shrink-0" stroke-width="2" />
          <span class="break-words">{{ err }}</span>
        </div>
      </div>

      <main class="flex-1 pb-24 lg:pb-6">
        <div class="max-w-md lg:max-w-2xl mx-auto px-4 py-6">
          <slot />
        </div>
      </main>
    </div>

    <!-- Mobile bottom nav -->
    <nav
      class="lg:hidden fixed bottom-0 inset-x-0 z-20 bg-ink-950/95 backdrop-blur border-t border-ink-700 flex"
      style="padding-bottom: env(safe-area-inset-bottom)"
    >
      <Link
        v-for="item in navItems"
        :key="item.href"
        :href="item.href"
        class="flex-1 flex flex-col items-center gap-1 py-2.5 text-xs transition-colors"
        :class="isActive(item.href) ? 'text-mint' : 'text-ink-400'"
      >
        <component :is="item.icon" class="w-5 h-5" stroke-width="2" />
        {{ item.label }}
      </Link>
    </nav>
  </div>
</template>
