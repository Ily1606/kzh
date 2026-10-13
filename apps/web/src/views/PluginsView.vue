<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { usePluginsStore } from '@/stores'
import type { PluginIndexCategory, PluginResource } from '@/api/generated/model'
import { SubmitPluginRequestCategory } from '@/api/generated/model'
import PluginCard from '@/components/pages/plugin/PluginCard.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Search, LoaderCircle, PackageOpen, ChevronDown, Check } from 'lucide-vue-next'

const store = usePluginsStore()
const route = useRoute()
const router = useRouter()

const categories = Object.values(SubmitPluginRequestCategory)

/** A hand-edited URL can hold anything, and the endpoint answers an unknown name with 422. */
function isCategory(value: unknown): value is PluginIndexCategory {
  return typeof value === 'string' && (categories as string[]).includes(value)
}

const selectedCategory = ref<PluginIndexCategory>(isCategory(route.query.category) ? route.query.category : null)

// UI state, not server state: this filters the page already in hand, so it
// belongs here rather than in the store.
const searchQuery = ref('')

const filteredPlugins = computed<PluginResource[]>(() => {
  const plugins = store.listPlugins

  if (!searchQuery.value.trim()) return plugins

  const q = searchQuery.value.toLowerCase().trim()
  return plugins.filter(
    (p) =>
      p.name?.toLowerCase().includes(q) ||
      p.title?.toLowerCase().includes(q) ||
      p.license?.toLowerCase().includes(q)
  )
})

async function fetchAllPlugins(page = 1) {
  await store.fetchList(page, selectedCategory.value)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

/** The category lives in the URL so the filtered view can be shared and reloaded. */
async function chooseCategory(category: PluginIndexCategory) {
  selectedCategory.value = category
  await router.replace({ query: category ? { category } : {} })
  await fetchAllPlugins(1)
}

onMounted(() => {
  fetchAllPlugins(1)
})
</script>

<template>
  <div class="py-8 space-y-8">

    <!-- Header & Search -->
    <div class="pb-6 border-b">
      <div>
        <div class="inline-flex items-center gap-2 py-1 text-xs font-medium mb-3">
          <PackageOpen class="size-7 text-primary " />
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
            placeholder="Search plugins ..."
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
        Showing <span class="font-medium text-foreground">{{ filteredPlugins.length }}</span>
        <template v-if="searchQuery">
          matching "<span class="font-medium text-foreground">{{ searchQuery }}</span>"
        </template>
        <template v-else>
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
    <div v-else-if="filteredPlugins.length > 0" class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
      <PluginCard
        v-for="plugin in filteredPlugins"
        :key="plugin.id"
        :plugin="plugin"
        class="min-w-0! max-w-none! w-full! shrink!"
      />
    </div>

    <!-- Empty State -->
    <div v-else class="py-16 text-center border rounded-2xl bg-card/60 p-8 space-y-3">
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
      v-if="!searchQuery && store.list.lastPage > 1"
      class="pt-6 flex items-center justify-center gap-4 border-t"
    >
      <Button
        variant="outline"
        :disabled="store.list.page === 1 || store.isFetchingList"
        @click="fetchAllPlugins(store.list.page - 1)"
      >
        Previous
      </Button>
      <span class="text-sm font-medium text-muted-foreground">
        Page {{ store.list.page }} of {{ store.list.lastPage }}
      </span>
      <Button
        variant="outline"
        :disabled="store.list.page === store.list.lastPage || store.isFetchingList"
        @click="fetchAllPlugins(store.list.page + 1)"
      >
        Next
      </Button>
    </div>
  </div>
</template>
