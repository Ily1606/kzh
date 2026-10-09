import { ref, type Ref } from 'vue'
import { getPluginEvent } from '@/api/generated/endpoints'

/**
 * One entry in a plugin's review timeline.
 *
 * Declared here rather than imported: the endpoint's response array comes back
 * as `unknown[]` because Scramble cannot infer a JsonResource collection
 * through `->resolve()`, so there is no generated type to lean on.
 */
export type PluginEventResource = {
  id: string
  event_type: string
  /** Written by an admin only. Null on the owner's own actions. */
  message: string | null
  created_at: string | null
}

/**
 * How a timeline row reads.
 *
 * Appearance only. Nothing here carries state about whether the owner still
 * owes a response — an entry is marked the same however old it is, because
 * "is anything outstanding" is not a property of a single row.
 */
export type TimelineEntry = {
  label: string
  icon: 'send' | 'refresh' | 'check' | 'x' | 'message'
  /** Tailwind classes for the icon chip. */
  chip: string
}

const ENTRIES: Record<string, TimelineEntry> = {
  created: {
    label: 'Submitted for review',
    icon: 'send',
    chip: 'bg-muted text-muted-foreground',
  },
  resubmitted: {
    label: 'Changes resubmitted for review',
    icon: 'refresh',
    chip: 'bg-muted text-muted-foreground',
  },
  approved: {
    label: 'Approved by an admin',
    icon: 'check',
    chip: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
  },
  rejected: {
    label: 'Rejected by an admin',
    icon: 'x',
    chip: 'bg-destructive/10 text-destructive',
  },
  update_requested: {
    label: 'Changes requested by an admin',
    icon: 'message',
    chip: 'bg-amber-500/10 text-amber-700 dark:text-amber-400',
  },
}

/**
 * An event type the backend adds later still renders: the raw value stands in
 * for the label, so an unrecognised row reads as "something happened" rather
 * than as a hole in the history.
 */
export function describeEvent(eventType: string): TimelineEntry {
  return (
    ENTRIES[eventType] ?? {
      label: eventType,
      icon: 'message',
      chip: 'bg-muted text-muted-foreground',
    }
  )
}

/**
 * One plugin's review timeline.
 */
export function usePluginEvents(pluginId: Ref<string>) {
  const { pluginEventIndex } = getPluginEvent()

  const events = ref<PluginEventResource[]>([])
  const isLoading = ref(false)
  const error = ref('')
  const currentPage = ref(1)
  const lastPage = ref(1)
  const total = ref(0)
  const sort = ref<'newest' | 'oldest'>('newest')

  async function load(page = 1): Promise<void> {
    isLoading.value = true
    error.value = ''

    try {
      const res = await pluginEventIndex(pluginId.value, { page, sort: sort.value })

      // Scramble cannot infer this endpoint's shape, so the collection comes
      // back untyped. The same cast plugins.store.ts makes for the plugin
      // list, whose response shape Scramble misses for the same reason.
      const body = res as unknown as {
        data?: { events?: PluginEventResource[] }
        meta?: { current_page?: number; last_page?: number; total?: number }
      }

      events.value = body.data?.events ?? []

      currentPage.value = body.meta?.current_page ?? page
      lastPage.value = body.meta?.last_page ?? 1
      total.value = body.meta?.total ?? events.value.length
    } catch (err) {
      const message = (err as { response?: { data?: { message?: string } } | null })?.response?.data?.message

      error.value =
        typeof message === 'string' && message.length > 0
          ? message
          : 'Failed to load the review history.'
    } finally {
      isLoading.value = false
    }
  }

  function reset(): void {
    events.value = []
    error.value = ''
    currentPage.value = 1
    lastPage.value = 1
    total.value = 0
  }

  return { events, isLoading, error, currentPage, lastPage, total, sort, load, reset }
}
