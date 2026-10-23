<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import {
  Flame,
  ArrowRight,
  ChevronLeft,
  ChevronRight,
  AlertCircle,
  Sparkles
} from '@lucide/vue'
import { usePluginsStore } from '@/stores'
import TrendingPluginRow from '@/components/pages/trending/TrendingPluginRow.vue'
import { Button } from '@/components/ui/button'
import { pluginConfig } from '@/config/plugins'

const route = useRoute()
const router = useRouter()
const store = usePluginsStore()

const PER_PAGE = pluginConfig.numberTrendingPluginPerPage

function parsePageQuery(): number {
  const raw = route.query.page
  const parsed = Number.parseInt(Array.isArray(raw) ? (raw[0] ?? '') : (raw ?? ''), 10)
  return Number.isNaN(parsed) || parsed < 1 ? 1 : parsed
}

const currentPage = ref(parsePageQuery())

const trendingPlugins = computed(() => store.trendingPlugins)
const totalItems = computed(() => store.trending.total || trendingPlugins.value.length)
const totalPages = computed(() =>
  Math.max(1, store.trending.lastPage > 1 ? store.trending.lastPage : Math.ceil(totalItems.value / PER_PAGE))
)

// If the list has more than PER_PAGE items (e.g. unpaginated API or mock), slice client-side.
// Otherwise, the backend already returned the slice for this page.
const paginatedPlugins = computed(() => {
  if (trendingPlugins.value.length > PER_PAGE) {
    const start = (currentPage.value - 1) * PER_PAGE
    return trendingPlugins.value.slice(start, start + PER_PAGE)
  }
  return trendingPlugins.value
})

async function syncQuery(page: number) {
  const query = { ...route.query }
  if (page > 1) {
    query.page = String(page)
  } else {
    delete query.page
  }

  await router.replace({ query })
}

async function loadData(page = currentPage.value, force = false) {
  await store.fetchTrending(page, force)
}

async function goToPage(p: number) {
  if (p < 1 || (totalPages.value > 0 && p > totalPages.value)) return
  currentPage.value = p
  await syncQuery(p)
  window.scrollTo({ top: 0, behavior: 'smooth' })
  await loadData(p)
}

watch(
  () => route.query.page,
  async () => {
    const pageFromQuery = parsePageQuery()
    if (pageFromQuery !== currentPage.value) {
      currentPage.value = pageFromQuery
      await loadData(pageFromQuery)
    }
  }
)

watch(totalPages, (max) => {
  if (max > 0 && currentPage.value > max) {
    goToPage(max)
  }
})

onMounted(() => {
  loadData(currentPage.value, false)
})
</script>

<template>
  <div class="py-10 sm:py-12 space-y-8 max-w-5xl mx-auto">
    <!-- Header: GitHub Trending style banner -->
    <div>
      <div class="inline-flex items-center gap-2 py-1 text-xs font-medium mb-3">
        <span class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
          <Sparkles class="size-5" />
        </span>
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">Trending Plugins</h1>
      </div>
      <p class="mt-2 text-muted-foreground max-w-xl">
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
      <p class="text-sm font-normal text-destructive">{{ store.fetchError }}</p>
      <Button variant="outline" size="sm" class="font-normal" data-test="retry-button" @click="loadData(currentPage, true)">
        Try again
      </Button>
    </div>

    <!-- Main Trending Container Box (GitHub Box Style) -->
    <div
      v-else
      class="rounded-xl border border-zinc-300 dark:border-zinc-700 bg-card text-card-foreground shadow-sm overflow-hidden"
    >
      <!-- Box Header Bar -->
      <div class="flex items-center justify-between px-5 py-3 bg-muted/60 border-b border-zinc-300 dark:border-zinc-700 text-xs font-normal text-foreground">
        <div class="flex items-center gap-2">
          <Flame class="size-4 text-amber-500" />
          <span class="font-normal text-foreground">Trending repositories</span>
          <span
            v-if="!store.isFetchingTrending"
            class="rounded-full bg-muted-foreground/15 text-foreground px-2 py-0.5 text-xs font-mono font-normal"
          >
            {{ totalItems }}
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
        <h3 class="text-base font-normal text-foreground">
          No trending plugins available
        </h3>
        <p class="text-sm font-normal text-foreground/75 max-w-sm mx-auto">
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
        <ol
          class="divide-y divide-zinc-300 dark:divide-zinc-700 transition-opacity"
          :class="{ 'opacity-60 pointer-events-none': store.isFetchingTrending }"
        >
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
          class="flex flex-col sm:flex-row items-center justify-between gap-3 px-5 py-3.5 border-t border-zinc-300 dark:border-zinc-700 bg-muted/30 text-xs font-normal"
          data-test="pagination-bar"
        >
          <div class="text-xs font-normal text-foreground/80">
            Showing
            <span class="font-normal text-foreground">{{ totalItems > 0 ? (currentPage - 1) * PER_PAGE + 1 : 0 }}</span>
            to
            <span class="font-normal text-foreground">{{ Math.min(currentPage * PER_PAGE, totalItems) }}</span>
            of
            <span class="font-normal text-foreground">{{ totalItems }}</span>
            plugins
          </div>

          <div class="flex items-center gap-1.5">
            <Button
              variant="outline"
              size="sm"
              class="h-8 gap-1 font-normal text-foreground"
              data-test="prev-page"
              :disabled="currentPage <= 1 || store.isFetchingTrending"
              @click="goToPage(currentPage - 1)"
            >
              <ChevronLeft class="size-3.5" />
              <span>Previous</span>
            </Button>

            <div class="hidden sm:flex items-center gap-1">
              <button
                v-for="p in totalPages"
                :key="p"
                class="size-8 rounded-md text-xs font-normal transition-colors"
                :class="p === currentPage ? 'bg-primary text-primary-foreground shadow-xs' : 'text-foreground hover:bg-muted'"
                :data-test="`page-button-${p}`"
                :disabled="store.isFetchingTrending"
                @click="goToPage(p)"
              >
                {{ p }}
              </button>
            </div>

            <span class="sm:hidden font-normal text-foreground px-2">
              Page {{ currentPage }} of {{ totalPages }}
            </span>

            <Button
              variant="outline"
              size="sm"
              class="h-8 gap-1 font-normal text-foreground"
              data-test="next-page"
              :disabled="currentPage >= totalPages || store.isFetchingTrending"
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
