<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import { ArrowLeft, Check, LoaderCircle, MessageSquare, RefreshCw, Send, X } from 'lucide-vue-next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { usePluginsStore } from '@/stores'
import { useAuth } from '@/composables/useAuth'
import { describeEvent, usePluginEvents, type TimelineEntry } from '@/composables/usePluginEvents'
import { formatRelativeDate } from '@/utils/date'

const route = useRoute()
const router = useRouter()
const store = usePluginsStore()
const auth = useAuth()

const pluginId = computed(() => route.params.id as string)
const plugin = computed(() => store.findById(pluginId.value))

const { events, isLoading, error, currentPage, lastPage, total, sort, load } = usePluginEvents(pluginId)

const ICONS = {
  send: Send,
  refresh: RefreshCw,
  check: Check,
  x: X,
  message: MessageSquare,
} as const

const showPagination = computed(() => lastPage.value > 1)

/** Narrowing the key type is what lets `ICONS[entry.icon]` type-check. */
function iconOf(entry: TimelineEntry) {
  return ICONS[entry.icon]
}

async function goToPage(page: number) {
  await load(page)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

async function changeSort() {
  sort.value = sort.value === 'newest' ? 'oldest' : 'newest'
  await load(1)
}

onMounted(async () => {
  await store.ensurePlugin(pluginId.value)

  if (!plugin.value || plugin.value.user_id !== auth.user?.id) {
    router.replace({ name: 'plugin-detail', params: { id: pluginId.value } })

    return
  }

  await load(1)
})

watch(pluginId, () => {
  load(1)
})
</script>

<template>
  <div class="mx-auto max-w-4xl py-12 px-4 sm:px-6">
    <div v-if="store.isFetchingPlugin && !plugin" class="text-center py-12">
      <div class="animate-pulse flex flex-col items-center">
        <div class="h-12 w-12 bg-muted rounded-full mb-4"></div>
        <div class="h-6 w-1/3 bg-muted rounded mb-2"></div>
      </div>
    </div>

    <div v-else class="space-y-8">
      <!-- Header -->
      <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 pb-6 border-b">
        <div class="space-y-3">
          <h1 class="text-3xl font-bold tracking-tight">History timeline</h1>

          <p class="text-lg text-muted-foreground">
            <template v-if="plugin">
              Every step in <span class="font-mono">{{ plugin.name }}</span>'s review.
            </template>
            <template v-else>
              Every step in this plugin's review.
            </template>
          </p>
        </div>

        <Button as-child variant="outline" class="shrink-0">
          <RouterLink :to="{ name: 'plugin-detail', params: { id: pluginId } }">
            <ArrowLeft class="size-4" />
            Back to plugin
          </RouterLink>
        </Button>
      </div>

      <!-- Loading -->
      <div v-if="isLoading && events.length === 0" class="flex flex-col items-center justify-center py-16">
        <LoaderCircle class="size-8 animate-spin text-primary mb-3" />
        <p class="text-muted-foreground text-sm">Loading the review history...</p>
      </div>

      <!-- Error -->
      <div v-else-if="error" class="py-12 text-center border rounded-2xl bg-card/60 p-8 space-y-3">
        <div class="mx-auto size-12 rounded-full bg-destructive/10 flex items-center justify-center text-destructive">
          <X class="size-6" />
        </div>
        <h2 class="text-lg font-semibold">Could not load the history</h2>
        <p class="text-sm text-muted-foreground max-w-sm mx-auto">{{ error }}</p>
        <Button variant="outline" size="sm" @click="load(1)">Try again</Button>
      </div>

      <!-- Empty -->
      <div v-else-if="events.length === 0" class="py-16 text-center border rounded-2xl bg-card/60 p-8 space-y-2">
        <div class="mx-auto size-12 rounded-full bg-muted flex items-center justify-center text-muted-foreground">
          <MessageSquare class="size-6" />
        </div>
        <h2 class="text-base font-semibold">No activity yet</h2>
        <p class="text-sm text-muted-foreground max-w-sm mx-auto">
          This plugin predates the review history, or nothing has happened to it yet.
        </p>
      </div>

      <template v-else>
        <!-- Sort -->
        <div class="flex items-center justify-between">
          <p class="text-sm text-muted-foreground">
            {{ total }} {{ total === 1 ? 'entry' : 'entries' }}
          </p>
          <button
            type="button"
            class="inline-flex cursor-pointer items-center gap-1.5 text-sm font-medium text-primary hover:underline"
            @click="changeSort"
          >
            {{ sort === 'newest' ? 'Newest first' : 'Oldest first' }}
          </button>
        </div>

        <!-- Timeline -->
        <ol class="space-y-5">
          <li v-for="event in events" :key="event.id" class="flex gap-4">
            <div class="relative flex shrink-0 flex-col items-center">
              <span
                class="z-10 grid size-10 place-items-center rounded-full shadow-lg"
                :class="describeEvent(event.event_type).tone.marker"
              >
                <component :is="iconOf(describeEvent(event.event_type))" class="size-5" />
              </span>

              <span
                v-if="event !== events[events.length - 1]"
                class="mt-2 w-0.5 flex-1 rounded-full"
                :class="describeEvent(event.event_type).tone.rail"
                aria-hidden="true"
              ></span>
            </div>

            <!-- Body -->
            <div
              class="min-w-0 flex-1 rounded-xl border p-4"
              :class="describeEvent(event.event_type).tone.surface"
            >
              <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <Badge :variant="describeEvent(event.event_type).tone.badge">
                  {{ describeEvent(event.event_type).label }}
                </Badge>
                <time
                  :datetime="event.created_at ?? undefined"
                  class="text-xs text-muted-foreground"
                >
                  {{ formatRelativeDate(event.created_at) }}
                </time>
              </div>

              <!-- The admin's message. Only reviewer actions carry one. -->
              <p
                v-if="event.message"
                class="mt-3 rounded-lg border-l-2 bg-background/70 p-3 text-sm text-foreground"
                :class="describeEvent(event.event_type).tone.accent"
              >
                {{ event.message }}
              </p>
            </div>
          </li>
        </ol>

        <!-- Pagination -->
        <div v-if="showPagination" class="flex items-center justify-center gap-4 border-t pt-6">
          <Button
            variant="outline"
            :disabled="isLoading || currentPage <= 1"
            @click="goToPage(currentPage - 1)"
          >
            Previous
          </Button>
          <span class="text-sm font-medium text-muted-foreground">
            Page {{ currentPage }} of {{ lastPage }}
          </span>
          <Button
            variant="outline"
            :disabled="isLoading || currentPage >= lastPage"
            @click="goToPage(currentPage + 1)"
          >
            Next
          </Button>
        </div>
      </template>
    </div>
  </div>
</template>
