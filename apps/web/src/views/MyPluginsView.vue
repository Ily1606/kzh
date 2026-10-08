<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { getPlugin } from '@/api/generated/endpoints'
import type { PluginResource } from '@/api/generated/model'
import { PluginStatus } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import MyPluginRow from '@/components/pages/plugin/MyPluginRow.vue'
import { notifyError } from '@/utils/toast'
import { pluginConfig } from '@/config/plugins'
import { AlertCircle, LoaderCircle, PackageOpen, Plus } from 'lucide-vue-next'

const { pluginMyPlugins } = getPlugin()
const route = useRoute()
const router = useRouter()

const PER_PAGE = pluginConfig.numberPluginPerPage

const STATUS_TABS: Array<{ value?: PluginStatus; label: string }> = [
  { label: 'All' },
  { value: PluginStatus.pending, label: 'Pending' },
  { value: PluginStatus.approved, label: 'Published' },
  { value: PluginStatus.rejected, label: 'Rejected' },
]

const VALID_STATUSES = new Set<PluginStatus>(Object.values(PluginStatus))

const plugins = ref<PluginResource[]>([])
const meta = ref<{ current_page: number; last_page: number; total: number } | null>(null)
const isFetching = ref(true)
const fetchError = ref<string | null>(null)


const allTabTotal = ref<number | null>(null)

const activeStatus = computed<PluginStatus | undefined>(() => {
  const raw = route.query.status
  const value = Array.isArray(raw) ? raw[0] : raw

  // An unknown value in the URL falls back to All rather than sending it to the
  // API and coming back with a 422 on a page load.
  return VALID_STATUSES.has(value as PluginStatus) ? (value as PluginStatus) : undefined
})

const activeTabIndex = computed(() => STATUS_TABS.findIndex((tab) => tab.value === activeStatus.value))

const activeTabLabel = computed(
  () => STATUS_TABS.find((tab) => tab.value === activeStatus.value)?.label ?? 'All',
)

const showPagination = computed(() => (meta.value?.last_page ?? 1) > 1)

function setStatus(value?: PluginStatus) {
  router.push({
    name: 'my-plugins',
    query: value ? { status: value } : {},
  })
}

function goToPage(page: number) {
  // replace, not push: paging through a list is not a history entry the Back
  // button should have to walk back through one click at a time.
  router.replace({
    name: 'my-plugins',
    query: { ...route.query, page: String(page) },
  })
}

async function fetchMyPlugins(page = 1) {
  isFetching.value = true
  fetchError.value = null

  try {
    const res = await pluginMyPlugins({
      page,
      per_page: PER_PAGE,
      status: activeStatus.value,
    })

    plugins.value = res.data ?? []
    meta.value = res.meta ?? null

    if (activeStatus.value === undefined) {
      allTabTotal.value = res.meta?.total ?? null
    }
  } catch (err: any) {
    plugins.value = []
    meta.value = null
    const message = err?.response?.data?.message || 'Could not load your plugins.'
    fetchError.value = message
    notifyError(message)
  } finally {
    isFetching.value = false
  }
}

function currentPageFromQuery(): number {
  const raw = route.query.page
  const parsed = Number.parseInt(Array.isArray(raw) ? (raw[0] ?? '') : (raw ?? ''), 10)

  return Number.isNaN(parsed) || parsed < 1 ? 1 : parsed
}

watch([activeStatus, () => route.query.page], () => {
  fetchMyPlugins(currentPageFromQuery())
}, { immediate: true })
</script>

<template>
  <div class="py-8 space-y-6">

    <!-- Header -->
    <div class="flex flex-col gap-6 pb-6 border-b sm:flex-row sm:items-end sm:justify-between">
      <div>
        <div class="inline-flex items-center gap-2 py-1 text-xs font-medium mb-3">
          <PackageOpen class="size-7 text-primary" />
          <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">Resources</h1>
        </div>
        <p class="mt-2 text-muted-foreground max-w-xl">
          Manage the plugins you have submitted to DSH and follow their review status.
        </p>
      </div>

      <Button as-child class="shrink-0">
        <RouterLink to="/publish"><Plus /> Publish new plugin</RouterLink>
      </Button>
    </div>

    <!-- Status Tabs -->
    <div role="tablist" aria-label="Filter plugins by review status" class="flex gap-6 border-b">
      <button
        v-for="(tab, index) in STATUS_TABS"
        :id="`resources-tab-${tab.value ?? 'all'}`"
        :key="tab.value ?? 'all'"
        type="button"
        role="tab"
        :aria-selected="index === activeTabIndex"
        aria-controls="resources-panel"
        :tabindex="index === activeTabIndex ? 0 : -1"
        class="relative flex items-center gap-1.5 pb-3 text-sm font-medium transition-colors cursor-pointer"
        :class="index === activeTabIndex ? 'text-foreground' : 'text-muted-foreground hover:text-foreground'"
        @click="setStatus(tab.value)"
      >
        {{ tab.label }}
        <span v-if="index === 0 && allTabTotal !== null" class="text-xs text-muted-foreground">
          {{ allTabTotal }}
        </span>
        <span
          v-if="index === activeTabIndex"
          class="absolute inset-x-0 -bottom-px h-0.5 rounded-full bg-primary"
        />
      </button>
    </div>

    <!-- List -->
    <div
      id="resources-panel"
      role="tabpanel"
      :aria-labelledby="`resources-tab-${activeStatus ?? 'all'}`"
    >
      <!-- Loading -->
      <div v-if="isFetching" class="flex flex-col items-center justify-center py-20">
        <LoaderCircle class="size-10 animate-spin text-primary mb-4" />
        <p class="text-muted-foreground text-sm">Loading your plugins...</p>
      </div>

      <!-- Error -->
      <div v-else-if="fetchError" class="py-16 text-center border rounded-2xl bg-card/60 p-8 space-y-3">
        <div class="mx-auto size-12 rounded-full bg-destructive/10 flex items-center justify-center text-destructive">
          <AlertCircle class="size-6" />
        </div>
        <h3 class="text-lg font-semibold">Something went wrong</h3>
        <p class="text-sm text-muted-foreground max-w-sm mx-auto">{{ fetchError }}</p>
        <Button variant="outline" size="sm" @click="fetchMyPlugins(currentPageFromQuery())">
          Try again
        </Button>
      </div>

      <!-- No plugins at all -->
      <div v-else-if="plugins.length === 0 && activeStatus === undefined" class="py-16 text-center border rounded-2xl bg-card/60 p-8 space-y-3">
        <div class="mx-auto size-12 rounded-full bg-muted flex items-center justify-center text-muted-foreground">
          <PackageOpen class="size-6" />
        </div>
        <h3 class="text-lg font-semibold">You haven't published any plugins yet</h3>
        <p class="text-sm text-muted-foreground max-w-sm mx-auto">
          Submit your first package and it will appear here while it waits for review.
        </p>
        <Button as-child class="mt-2">
          <RouterLink to="/publish"><Plus /> Publish your first plugin</RouterLink>
        </Button>
      </div>

      <!-- Nothing in this particular tab -->
      <div v-else-if="plugins.length === 0" class="py-16 text-center border rounded-2xl bg-card/60 p-8 space-y-2">
        <h3 class="text-base font-semibold">No {{ activeTabLabel.toLowerCase() }} plugins</h3>
        <p class="text-sm text-muted-foreground">
          Switch to another tab to see the rest of your plugins.
        </p>
      </div>

      <template v-else>
        <ul class="rounded-2xl border bg-card/60 overflow-hidden">
          <MyPluginRow v-for="plugin in plugins" :key="plugin.id" :plugin="plugin" />
        </ul>

        <!-- Pagination -->
        <div v-if="showPagination" class="pt-2 flex items-center justify-center gap-4 border-t">
          <Button
            variant="outline"
            :disabled="isFetching || (meta?.current_page ?? 1) <= 1"
            @click="goToPage((meta?.current_page ?? 1) - 1)"
          >
            Previous
          </Button>
          <span class="text-sm font-medium text-muted-foreground">
            Page {{ meta?.current_page }} of {{ meta?.last_page }}
          </span>
          <Button
            variant="outline"
            :disabled="isFetching || (meta?.current_page ?? 1) >= (meta?.last_page ?? 1)"
            @click="goToPage((meta?.current_page ?? 1) + 1)"
          >
            Next
          </Button>
        </div>
      </template>
    </div>
  </div>
</template>
