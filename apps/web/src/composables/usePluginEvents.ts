import { ref, type Ref } from 'vue'
import { getPluginEvent } from '@/api/generated/endpoints'

export type PluginEventResource = {
  id: string
  event_type: string
  /** Written by an admin only. Null on the owner's own actions. */
  message: string | null
  created_at: string | null
}

export type TimelineTone = {
  /** The filled marker, and the shadow it casts. */
  marker: string
  /** The connector behind it. */
  rail: string
  /** The card's tint and border. */
  surface: string
  /**
   * The accent bar down the left of an admin's message.
   */
  accent: string
  /**
   * The badge variant the event's name renders as.
   */
  badge: 'default' | 'secondary' | 'outline'
}

export type TimelineEntry = {
  label: string
  icon: 'send' | 'refresh' | 'check' | 'x' | 'message'
  tone: TimelineTone
}

const ENTRIES: Record<string, TimelineEntry> = {
  created: {
    label: 'Submitted for review',
    icon: 'send',
    tone: {
      marker: 'bg-primary text-primary-foreground shadow-primary/25',
      rail: 'bg-primary/30',
      accent: 'border-primary',
      surface: 'border-primary/20 bg-primary/[0.04]',
      badge: 'default',
    },
  },
  resubmitted: {
    label: 'Changes resubmitted for review',
    icon: 'refresh',
    tone: {
      marker: 'bg-primary text-primary-foreground shadow-primary/25',
      rail: 'bg-primary/30',
      accent: 'border-primary',
      surface: 'border-primary/20 bg-primary/[0.04]',
      badge: 'default',
    },
  },
  update_requested: {
    label: 'Changes requested by an admin',
    icon: 'message',
    tone: {
      marker: 'bg-foreground text-background shadow-foreground/20',
      rail: 'bg-foreground/25',
      accent: 'border-foreground',
      surface: 'border-border bg-muted',
      badge: 'outline',
    },
  },
  approved: {
    label: 'Approved by an admin',
    icon: 'check',
    tone: {
      marker: 'bg-foreground text-background shadow-foreground/25',
      rail: 'bg-foreground/30',
      accent: 'border-foreground',
      surface: 'border-foreground/25 bg-secondary',
      badge: 'secondary',
    },
  },
  rejected: {
    label: 'Rejected by an admin',
    icon: 'x',
    tone: {
      // Rejection is drawn as an outlined, empty marker rather than a filled
      // one. It is the absence of an outcome, so it must not compete with the
      // filled black of an approval for attention.
      marker: 'bg-background text-foreground border-2 border-foreground shadow-none',
      rail: 'bg-border',
      accent: 'border-foreground',
      surface: 'border-border bg-muted',
      badge: 'outline',
    },
  },
}

const NEUTRAL_TONE: TimelineTone = {
  marker: 'bg-muted text-muted-foreground border border-border',
  rail: 'bg-border',
  accent: 'border-border',
  surface: 'border-border bg-muted/30',
  badge: 'outline',
}

export function describeEvent(eventType: string): TimelineEntry {
  return ENTRIES[eventType] ?? { label: eventType, icon: 'message', tone: NEUTRAL_TONE }
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
