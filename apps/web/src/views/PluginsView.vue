<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { getPlugin } from '@/api/generated/endpoints'
import type { PluginResource } from '@/api/generated/model'
import PluginCard from '@/components/PluginCard.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Search, LoaderCircle, PackageOpen, ArrowLeft } from 'lucide-vue-next'

const { pluginIndex } = getPlugin()

const allPlugins = ref<PluginResource[]>([])
const allPluginsMeta = ref<any>(null)
const currentPage = ref(1)
const isFetchingPlugins = ref(true)
const searchQuery = ref('')
const perPage = ref(9)

async function fetchAllPlugins(page = 1) {
  isFetchingPlugins.value = true
  try {
    const res = await pluginIndex({ params: { page, per_page: perPage.value } })
    allPlugins.value = res.data
    allPluginsMeta.value = res.meta
    currentPage.value = page
    window.scrollTo({ top: 0, behavior: 'smooth' })
  } catch (e) {
    console.error('Failed to fetch paginated plugins', e)
  } finally {
    isFetchingPlugins.value = false
  }
}

const filteredPlugins = computed(() => {
  if (!searchQuery.value.trim()) return allPlugins.value
  const q = searchQuery.value.toLowerCase().trim()
  return allPlugins.value.filter(
    (p) =>
      p.name?.toLowerCase().includes(q) ||
      p.title?.toLowerCase().includes(q) ||
      p.license?.toLowerCase().includes(q)
  )
})

onMounted(() => {
  fetchAllPlugins(1)
})
</script>

<template>
  <div class="py-10 space-y-8">
    <!-- Breadcrumb / Back button -->
    <div class="flex items-center gap-2 text-sm text-muted-foreground">
      <RouterLink to="/" class="inline-flex items-center gap-1 hover:text-foreground transition-colors">
        <ArrowLeft class="size-4" /> Back to Home
      </RouterLink>
    </div>

    <!-- Header & Search -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 pb-6 border-b">
      <div>
        <div class="inline-flex items-center gap-2 rounded-full border bg-background px-3 py-1 text-xs font-medium text-muted-foreground shadow-xs mb-3">
          <PackageOpen class="size-3.5 text-primary" /> Plugin Registry Catalogue
        </div>
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">All Plugins</h1>
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
    <div v-if="allPluginsMeta" class="flex items-center justify-between text-sm text-muted-foreground">
      <span>
        Showing <span class="font-medium text-foreground">{{ filteredPlugins.length }}</span>
        <template v-if="searchQuery">
          matching "<span class="font-medium text-foreground">{{ searchQuery }}</span>"
        </template>
        <template v-else>
          of <span class="font-medium text-foreground">{{ allPluginsMeta.total ?? allPlugins.length }}</span> total plugins
        </template>
      </span>
      <span v-if="allPluginsMeta.last_page > 1">
        Page {{ currentPage }} of {{ allPluginsMeta.last_page }}
      </span>
    </div>

    <!-- Loading State -->
    <div v-if="isFetchingPlugins" class="flex flex-col items-center justify-center py-20">
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
      v-if="!searchQuery && allPluginsMeta && allPluginsMeta.last_page > 1"
      class="pt-6 flex items-center justify-center gap-4 border-t"
    >
      <Button
        variant="outline"
        :disabled="currentPage === 1 || isFetchingPlugins"
        @click="fetchAllPlugins(currentPage - 1)"
      >
        Previous
      </Button>
      <span class="text-sm font-medium text-muted-foreground">
        Page {{ currentPage }} of {{ allPluginsMeta.last_page }}
      </span>
      <Button
        variant="outline"
        :disabled="currentPage === allPluginsMeta.last_page || isFetchingPlugins"
        @click="fetchAllPlugins(currentPage + 1)"
      >
        Next
      </Button>
    </div>
  </div>
</template>
