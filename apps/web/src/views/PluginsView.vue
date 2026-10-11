<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePluginsStore } from '@/stores'
import type { PluginResource } from '@/api/generated/model'
import PluginCard from '@/components/pages/plugin/PluginCard.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Search, LoaderCircle, PackageOpen } from 'lucide-vue-next'

const store = usePluginsStore()

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
  await store.fetchList(page)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(() => {
  fetchAllPlugins(1)
})
</script>

<template>
  <div class="py-8 space-y-8">

    <!-- Header & Search -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 pb-6 border-b">
      <div>
        <div class="inline-flex items-center gap-2 py-1 text-xs font-medium mb-3">
          <PackageOpen class="size-7 text-primary " />
          <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">Plugins</h1>
        </div>
        <p class="mt-2 text-muted-foreground max-w-xl">
          Browse and discover all packages published by the community for DeepSeek Harness.
        </p>
      </div>

      <!-- Search Box -->
      <div class="w-full md:w-80 relative">
        <Search class="size-4 absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
        <Input
          v-model="searchQuery"
          type="text"
          placeholder="Filter plugins by name..."
          class="pl-9"
        />
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
        {{ searchQuery ? `We couldn't find any plugin matching "${searchQuery}". Try a different keyword.` : 'There are currently no published plugins available.' }}
      </p>
      <Button v-if="searchQuery" variant="outline" size="sm" @click="searchQuery = ''">
        Clear search
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
