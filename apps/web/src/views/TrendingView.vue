<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import {
  Flame,
  ArrowRight,
  ChevronLeft,
  ChevronRight,
  AlertCircle
} from '@lucide/vue'
import { usePluginsStore } from '@/stores'
import TrendingPluginRow from '@/components/pages/trending/TrendingPluginRow.vue'
import { Button } from '@/components/ui/button'
import { pluginConfig } from '@/config/plugins'

const route = useRoute()
const router = useRouter()
const store = usePluginsStore()

const PER_PAGE = pluginConfig.numberTrendingPluginPerPage

const currentPage = ref(typeof route.query.page === 'string' ? Math.max(1, parseInt(route.query.page, 10) || 1) : 1)

const trendingPlugins = computed(() => store.trendingPlugins)

const totalPages = computed(() => Math.max(1, Math.ceil(trendingPlugins.value.length / PER_PAGE)))

const paginatedPlugins = computed(() => {
  const start = (currentPage.value - 1) * PER_PAGE
  return trendingPlugins.value.slice(start, start + PER_PAGE)
})

function syncQuery() {
  const query: Record<string, string> = {}
  if (currentPage.value > 1) query.page = String(currentPage.value)

  router.replace({ query })
}

function goToPage(p: number) {
  if (p < 1 || p > totalPages.value) return
  currentPage.value = p
  syncQuery()
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function loadData(force = false) {
  await store.fetchTrending(force)
}

watch(totalPages, (max) => {
  if (currentPage.value > max) {
    currentPage.value = max
    syncQuery()
  }
})

onMounted(() => {
  loadData(false)
})
</script>

<template>
  <div class="py-10 sm:py-12 space-y-8 max-w-5xl mx-auto">
    <!-- Header: GitHub Trending style banner -->
    <div class="text-center sm:text-left pb-6 border-b border-zinc-300 dark:border-zinc-700">
      <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-foreground">
        Trending Plugins
      </h1>
      <p class="mt-2 text-foreground/85 font-medium text-sm sm:text-base max-w-2xl leading-relaxed">
        See what the developer community is most excited about today. Ranked by recent engagement, stars, and views.
      </p>
    </div>

    <!-- Error State -->
    <div
      v-if="store.fetchError && !trendingPlugins.length"
      class="rounded-xl border border-destructive/40 bg-destructive/10 p-6 text-center space-y-3"
      data-test="error-banner"
    >
      <AlertCircle class="size-8 mx-auto text-destructive" />
      <p class="text-sm font-semibold text-destructive">{{ store.fetchError }}</p>
      <Button variant="outline" size="sm" class="font-semibold" data-test="retry-button" @click="loadData(true)">
        Try again
      </Button>
    </div>

    <!-- Main Trending Container Box (GitHub Box Style) -->
    <div
      v-else
      class="rounded-xl border border-zinc-300 dark:border-zinc-700 bg-card text-card-foreground shadow-sm overflow-hidden"
    >
      <!-- Box Header Bar -->
      <div class="flex items-center justify-between px-5 py-3 bg-muted/60 border-b border-zinc-300 dark:border-zinc-700 text-xs font-semibold text-foreground">
        <div class="flex items-center gap-2">
          <Flame class="size-4 text-amber-500" />
          <span class="font-bold text-foreground">Trending repositories</span>
          <span
            v-if="!store.isFetchingTrending"
            class="rounded-full bg-muted-foreground/15 text-foreground px-2 py-0.5 text-xs font-mono font-bold"
          >
            {{ trendingPlugins.length }}
          </span>
        </div>
      </div>

      <!-- Loading Skeleton (when fetching and empty) -->
      <div
        v-if="store.isFetchingTrending && !trendingPlugins.length"
        class="divide-y divide-zinc-300 dark:divide-zinc-700"
      >
        <div
          v-for="i in 5"
          :key="i"
          class="px-4 py-4.5 sm:px-6 sm:py-5 space-y-3 animate-pulse"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3 w-2/3">
              <div class="size-6 rounded-full bg-muted" />
              <div class="h-4 w-48 rounded bg-muted" />
            </div>
            <div class="h-8 w-20 rounded bg-muted" />
          </div>
          <div class="h-3.5 w-4/5 rounded bg-muted/60" />
          <div class="flex gap-4 pt-0.5">
            <div class="h-3 w-16 rounded bg-muted/50" />
            <div class="h-3 w-20 rounded bg-muted/50" />
            <div class="h-3 w-14 rounded bg-muted/50" />
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div
        v-else-if="!trendingPlugins.length"
        class="py-16 px-4 text-center space-y-3"
      >
        <div class="size-12 rounded-full bg-muted/60 grid place-items-center mx-auto text-muted-foreground">
          <Flame class="size-6 text-muted-foreground/60" />
        </div>
        <h3 class="text-base font-semibold text-foreground">
          No trending plugins available
        </h3>
        <p class="text-sm font-medium text-foreground/75 max-w-sm mx-auto">
          There are currently no trending packages. Be the first to publish a popular package!
        </p>
        <div class="pt-2 flex justify-center gap-3">
          <Button as-child size="sm" variant="default">
            <RouterLink to="/plugins">
              Explore all plugins <ArrowRight class="size-3.5 ml-1" />
            </RouterLink>
          </Button>
        </div>
      </div>

      <!-- Trending List -->
      <template v-else>
        <ol class="divide-y divide-zinc-300 dark:divide-zinc-700">
          <li v-for="(plugin, index) in paginatedPlugins" :key="plugin.id">
            <TrendingPluginRow
              :plugin="plugin"
              :rank="(currentPage - 1) * PER_PAGE + index + 1"
            />
          </li>
        </ol>

        <!-- Pagination Controls (10 plugins per page) -->
        <div
          v-if="totalPages > 1"
          class="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-3.5 border-t border-zinc-300 dark:border-zinc-700 bg-muted/30 text-xs"
          data-test="pagination-bar"
        >
          <div class="text-xs font-semibold text-foreground/80">
            Showing
            <span class="font-bold text-foreground">{{ (currentPage - 1) * PER_PAGE + 1 }}</span>
            to
            <span class="font-bold text-foreground">{{ Math.min(currentPage * PER_PAGE, trendingPlugins.length) }}</span>
            of
            <span class="font-bold text-foreground">{{ trendingPlugins.length }}</span>
            plugins
          </div>

          <div class="flex items-center gap-1.5">
            <Button
              variant="outline"
              size="sm"
              class="h-8 gap-1 font-bold text-foreground"
              data-test="prev-page"
              :disabled="currentPage <= 1"
              @click="goToPage(currentPage - 1)"
            >
              <ChevronLeft class="size-3.5" />
              <span>Previous</span>
            </Button>

            <div class="hidden sm:flex items-center gap-1">
              <button
                v-for="p in totalPages"
                :key="p"
                class="size-8 rounded-md text-xs font-bold transition-colors"
                :class="p === currentPage ? 'bg-primary text-primary-foreground shadow-xs' : 'text-foreground hover:bg-muted'"
                :data-test="`page-button-${p}`"
                @click="goToPage(p)"
              >
                {{ p }}
              </button>
            </div>

            <span class="sm:hidden font-bold text-foreground px-2">
              Page {{ currentPage }} of {{ totalPages }}
            </span>

            <Button
              variant="outline"
              size="sm"
              class="h-8 gap-1 font-bold text-foreground"
              data-test="next-page"
              :disabled="currentPage >= totalPages"
              @click="goToPage(currentPage + 1)"
            >
              <span>Next</span>
              <ChevronRight class="size-3.5" />
            </Button>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>
