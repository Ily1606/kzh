<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePluginsStore, useTaxonomyStore } from '@/stores'
import PluginCard from '@/components/pages/plugin/PluginCard.vue'
import Reveal from '@/components/common/Reveal.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Search, LoaderCircle, PackageOpen, ChevronDown, Check } from '@lucide/vue'
import { pluginIndexQuerySearchMax } from '@/api/generated/zod'

const store = usePluginsStore()
const route = useRoute()
const router = useRouter()

const taxonomy = useTaxonomyStore()
const categories = computed(() => taxonomy.categories)

/** A hand-edited URL can hold anything, and the endpoint answers an unknown name with 422. */
function isCategory(value: unknown): value is string {
  return typeof value === 'string' && taxonomy.categories.includes(value)
}

const selectedCategory = ref<string | null>(isCategory(route.query.category) ? route.query.category : null)
const searchQuery = ref(typeof route.query.search === 'string' ? route.query.search : '')
const SEARCH_DEBOUNCE_MS = 300

async function fetchAllPlugins(page = 1) {
  await store.fetchList(page, selectedCategory.value, searchQuery.value || null)
}

/** A new page of results is at the top of the list, so the reader goes there.
 *  Only for changes the reader asked for: scrolling on mount would undo the
 *  position restored when coming back to this page. */
async function goToPage(page: number) {
  await fetchAllPlugins(page)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function chooseCategory(category: string | null) {
  selectedCategory.value = category
  await syncQuery()
  await goToPage(1)
}

/** Reads the current filter state and writes it to the URL, so both filters
 *  stay in step after either one changes. */
async function syncQuery() {
  await router.replace({
    query: {
      ...(selectedCategory.value ? { category: selectedCategory.value } : {}),
      ...(searchQuery.value.trim() ? { search: searchQuery.value.trim() } : {}),
    },
  })
}

watch(searchQuery, (_query, _previous, onCleanup) => {
  const timer = setTimeout(async () => {
    await syncQuery()
    await goToPage(1)
  }, SEARCH_DEBOUNCE_MS)

  onCleanup(() => clearTimeout(timer))
})

onMounted(async () => {
  // The URL can name a category before the list arrives, so the check has to
  // wait for it. Fetching first would send no category and quietly drop it.
  await taxonomy.ensureLoaded()

  selectedCategory.value = isCategory(route.query.category) ? route.query.category : null

  fetchAllPlugins(1)
})
</script>

<template>
  <div class="py-12 space-y-8">

    <!-- Header & Search -->
    <div class="pb-6 border-b">
      <div>
        <div class="inline-flex items-center gap-2 py-1 text-xs font-medium mb-3">
          <span class="grid size-10 place-items-center rounded-xl bg-primary/10 text-primary">
            <PackageOpen class="size-5" />
          </span>
          <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">Plugins</h1>
        </div>
        <p class="mt-2 text-muted-foreground max-w-xl">
          Browse and discover all packages published by the community for DeepSeek Harness.
        </p>
      </div>

      <!-- Search Box & Category Filter -->
      <div class="mt-6 flex flex-col sm:flex-row gap-3">
        <div class="relative shrink-0 sm:w-80">
          <Search class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <Input
            v-model="searchQuery"
            type="text"
            :maxlength="pluginIndexQuerySearchMax"
            placeholder="Search plugins ..."
            data-test="search-input"
            class="h-11 rounded-xl pl-9"
          />
        </div>

        <Popover>
          <PopoverTrigger
            data-test="category-trigger"
            class="flex h-11 w-full shrink-0 cursor-pointer items-center justify-between gap-2 rounded-xl border border-border/60 bg-card px-3 text-sm font-medium text-foreground transition-colors hover:bg-accent focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40 sm:w-52"
          >
            <span class="truncate" :class="{ 'text-muted-foreground': !selectedCategory }">
              {{ selectedCategory ?? 'All categories' }}
            </span>
            <ChevronDown class="size-4 shrink-0 text-muted-foreground" />
          </PopoverTrigger>

          <PopoverContent align="end" class="w-[var(--reka-popover-trigger-width)] p-1">
            <button
              type="button"
              data-test="category-option-all"
              class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
              @click="chooseCategory(null)"
            >
              <span>All categories</span>
              <Check v-if="!selectedCategory" class="size-4 text-primary" />
            </button>
            <button
              v-for="category in categories"
              :key="category"
              type="button"
              :data-test="`category-option-${category}`"
              class="flex w-full cursor-pointer items-center justify-between gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors hover:bg-accent hover:text-accent-foreground"
              @click="chooseCategory(category)"
            >
              <span>{{ category }}</span>
              <Check v-if="selectedCategory === category" class="size-4 text-primary" />
            </button>
          </PopoverContent>
        </Popover>
      </div>
    </div>

    <!-- Stats summary -->
    <div class="flex items-center justify-between text-sm text-muted-foreground">
      <span>
        <template v-if="searchQuery">
          <span class="font-medium text-foreground">{{ store.list.total }}</span>
          matching "<span class="font-medium text-foreground">{{ searchQuery }}</span>"
        </template>
        <template v-else>
          Showing <span class="font-medium text-foreground">{{ store.listPlugins.length }}</span>
          of <span class="font-medium text-foreground">{{ store.list.total }}</span> total plugins
        </template>
      </span>
      <span v-if="store.list.lastPage > 1">
        Page {{ store.list.page }} of {{ store.list.lastPage }}
      </span>
    </div>

    <!-- Loading State -->
    <div v-if="store.isFetchingList" class="flex flex-col items-center justify-center py-20">
      <LoaderCircle class="size-10 animate-spin text-primary mb-4" />
      <p class="text-muted-foreground text-sm">Loading plugins catalogue...</p>
    </div>

    <!-- Plugin Grid -->
    <div v-else-if="store.listPlugins.length > 0" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <Reveal
        v-for="(plugin, index) in store.listPlugins"
        :key="plugin.id"
        :delay="(index % 9) * 0.05"
        :y="16"
      >
        <PluginCard
          :plugin="plugin"
          class="min-w-0! max-w-none! w-full! shrink!"
        />
      </Reveal>
    </div>

    <!-- Empty State -->
    <div v-else class="glass py-16 text-center rounded-2xl p-8 space-y-3">
      <div class="mx-auto size-12 rounded-full bg-muted flex items-center justify-center text-muted-foreground">
        <PackageOpen class="size-6" />
      </div>
      <h3 class="text-lg font-semibold">No plugins found</h3>
      <p class="text-sm text-muted-foreground max-w-sm mx-auto">
        <template v-if="searchQuery">
          We couldn't find any plugin matching "{{ searchQuery }}". Try a different keyword.
        </template>
        <template v-else-if="selectedCategory">
          There are no plugins in {{ selectedCategory }} yet.
        </template>
        <template v-else>
          There are currently no published plugins available.
        </template>
      </p>
      <Button v-if="searchQuery" variant="outline" size="sm" @click="searchQuery = ''">
        Clear search
      </Button>
      <Button v-else-if="selectedCategory" variant="outline" size="sm" @click="chooseCategory(null)">
        Show all categories
      </Button>
    </div>

    <!-- Pagination Controls -->
    <div
      v-if="store.list.lastPage > 1"
      class="pt-6 flex items-center justify-center gap-4 border-t"
    >
      <Button
        variant="outline"
        :disabled="store.list.page === 1 || store.isFetchingList"
        @click="goToPage(store.list.page - 1)"
      >
        Previous
      </Button>
      <span class="text-sm font-medium text-muted-foreground">
        Page {{ store.list.page }} of {{ store.list.lastPage }}
      </span>
      <Button
        variant="outline"
        :disabled="store.list.page === store.list.lastPage || store.isFetchingList"
        @click="goToPage(store.list.page + 1)"
      >
        Next
      </Button>
    </div>
  </div>
</template>
