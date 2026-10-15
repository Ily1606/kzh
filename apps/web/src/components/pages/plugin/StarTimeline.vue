<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { getStar } from '@/api/generated/endpoints/star/star'
import type { StarTimeline200, StarTimeline200Data } from '@/api/generated/model'

import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { usePluginsStore } from '@/stores'
import { Star } from '@lucide/vue'

const props = defineProps<{
  pluginId: string
  starCount?: number
  isStarred?: boolean
}>()

interface TimelineData {
  date: string
  count: number
}

interface StarPoint {
  date: string
  timestamp: number
  stars: number
  cumulative: number
  count: number
  label: string
  fullLabel: string
}

const store = usePluginsStore()
const storePlugin = computed(() => store.findById(props.pluginId))

const currentStarCount = computed(() => props.starCount ?? storePlugin.value?.star_count)
const currentIsStarred = computed(() => props.isStarred ?? storePlugin.value?.is_star)

const timeline = ref<TimelineData[]>([])
const isLoading = ref(true)
const error = ref('')
let isInitialized = false

const fetchTimeline = async (silent = false) => {
  if (!props.pluginId) return
  if (!silent) isLoading.value = true
  error.value = ''
  try {
    const res: StarTimeline200 = await getStar().starTimeline(props.pluginId)
    const rawData: StarTimeline200Data = res.data
    if (Array.isArray(rawData)) {
      timeline.value = rawData as unknown as TimelineData[]
    } else {
      timeline.value = []
    }
  } catch {
    error.value = 'Failed to load timeline data'
  } finally {
    if (!silent) isLoading.value = false
  }
}

onMounted(async () => {
  await fetchTimeline()
  isInitialized = true
})

watch(() => props.pluginId, async () => {
  isInitialized = false
  await fetchTimeline()
  isInitialized = true
})

const getTodayDateStr = () => {
  const now = new Date()
  const year = now.getUTCFullYear()
  const month = String(now.getUTCMonth() + 1).padStart(2, '0')
  const day = String(now.getUTCDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const applyOptimisticStarChange = (diff: number) => {
  if (diff === 0) return
  const todayStr = getTodayDateStr()
  const monthStr = todayStr.slice(0, 7)

  const idx = timeline.value.findIndex(
    item => item.date === todayStr || (item.date.length === 7 && item.date === monthStr)
  )

  if (diff > 0) {
    if (idx !== -1) {
      timeline.value[idx].count += diff
    } else {
      const isMonthFormat = timeline.value.length > 0 && timeline.value[0].date.length === 7
      timeline.value.push({
        date: isMonthFormat ? monthStr : todayStr,
        count: diff,
      })
    }
  } else if (diff < 0) {
    if (idx !== -1) {
      const newCount = timeline.value[idx].count + diff
      if (newCount <= 0) {
        timeline.value.splice(idx, 1)
      } else {
        timeline.value[idx].count = newCount
      }
    }
  }
}

// Watch for star / unstar changes to update immediately and re-sync
watch(
  [() => currentIsStarred.value, () => currentStarCount.value],
  async ([newStarred, newCount], [oldStarred, oldCount]) => {
    if (!isInitialized) return
    if (newStarred === oldStarred && newCount === oldCount) return

    const diff = (newCount ?? 0) - (oldCount ?? 0)
    applyOptimisticStarChange(diff !== 0 ? diff : (newStarred ? 1 : -1))

    // Silently re-fetch from API to ensure sync with server
    await fetchTimeline(true)
  }
)

const parseTimestamp = (dateStr: string): number => {
  if (!dateStr) return 0
  if (dateStr.length === 7) {
    const [y, m] = dateStr.split('-').map(Number)
    return Date.UTC(y, (m || 1) - 1, 1)
  }
  const [y, m, d] = dateStr.split('-').map(Number)
  return Date.UTC(y, (m || 1) - 1, d || 1)
}

/**
 * Fills in missing dates/months with count: 0 between timeline start and the current date (today)
 */
const fillMissingDates = (rawTimeline: TimelineData[]): TimelineData[] => {
  if (!rawTimeline || rawTimeline.length === 0) {
    return []
  }

  const validItems = rawTimeline.filter(item => item && item.date && typeof item.count === 'number')
  if (validItems.length === 0) {
    return []
  }

  const sorted = [...validItems].sort((a, b) => a.date.localeCompare(b.date))
  const first = sorted[0].date
  const rawLast = sorted[sorted.length - 1].date

  const isMonth = first.length === 7
  const todayStr = getTodayDateStr()
  const todayMonthStr = todayStr.slice(0, 7)

  // Extend up to current date/month if last recorded date is earlier than today
  let last = rawLast
  if (isMonth) {
    if (last < todayMonthStr) {
      last = todayMonthStr
    }
  } else {
    if (last < todayStr) {
      last = todayStr
    }
  }

  if (first === last && sorted.length === 1) {
    return sorted
  }

  const countMap = new Map<string, number>()
  for (const item of sorted) {
    countMap.set(item.date, (countMap.get(item.date) || 0) + item.count)
  }

  const filled: TimelineData[] = []

  if (isMonth) {
    const [startYear, startMonth] = first.split('-').map(Number)
    const [endYear, endMonth] = last.split('-').map(Number)
    if (!startYear || !startMonth || !endYear || !endMonth) return sorted

    const current = new Date(Date.UTC(startYear, startMonth - 1, 1))
    const end = new Date(Date.UTC(endYear, endMonth - 1, 1))

    let safetyLimit = 600 // max 50 years
    while (current <= end && safetyLimit-- > 0) {
      const year = current.getUTCFullYear()
      const month = String(current.getUTCMonth() + 1).padStart(2, '0')
      const dateStr = `${year}-${month}`

      filled.push({
        date: dateStr,
        count: countMap.get(dateStr) ?? 0,
      })

      current.setUTCMonth(current.getUTCMonth() + 1)
    }
  } else {
    const [startYear, startMonth, startDay] = first.split('-').map(Number)
    const [endYear, endMonth, endDay] = last.split('-').map(Number)
    if (!startYear || !startMonth || !startDay || !endYear || !endMonth || !endDay) return sorted

    const current = new Date(Date.UTC(startYear, startMonth - 1, startDay))
    const end = new Date(Date.UTC(endYear, endMonth - 1, endDay))

    let safetyLimit = 4000 // max ~10 years
    while (current <= end && safetyLimit-- > 0) {
      const year = current.getUTCFullYear()
      const month = String(current.getUTCMonth() + 1).padStart(2, '0')
      const day = String(current.getUTCDate()).padStart(2, '0')
      const dateStr = `${year}-${month}-${day}`

      filled.push({
        date: dateStr,
        count: countMap.get(dateStr) ?? 0,
      })

      current.setUTCDate(current.getUTCDate() + 1)
    }
  }

  return filled.length ? filled : sorted
}

const formatLabel = (dateStr: string) => {
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  if (dateStr.length === 7) return d.toLocaleDateString('en-US', { month: 'short', year: 'numeric' })
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const formatFull = (dateStr: string) => {
  const d = new Date(dateStr)
  if (isNaN(d.getTime())) return dateStr
  if (dateStr.length === 7) return d.toLocaleDateString('en-US', { month: 'long', year: 'numeric' })
  return d.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' })
}

// Cumulative data for the chart, zero-filled for missing dates up to today
const cumulativeTimeline = computed<StarPoint[]>(() => {
  let total = 0
  const filled = fillMissingDates(timeline.value)

  return filled
    .map(item => {
      total += item.count
      return {
        date: item.date,
        timestamp: parseTimestamp(item.date),
        stars: total,
        cumulative: total,
        count: item.count,
        label: formatLabel(item.date),
        fullLabel: formatFull(item.date),
      }
    })
    .sort((a, b) => a.timestamp - b.timestamp)
})

const totalStars = computed(() => {
  if (currentStarCount.value !== undefined) {
    return currentStarCount.value
  }
  const arr = cumulativeTimeline.value
  return arr.length > 0 ? arr[arr.length - 1].cumulative : 0
})

const maxCount = computed(() => {
  const arr = cumulativeTimeline.value
  const calculatedTotal = arr.length > 0 ? arr[arr.length - 1].cumulative : 0
  return Math.max(totalStars.value, calculatedTotal, 1)
})

const minTime = computed(() => {
  const arr = cumulativeTimeline.value
  return arr.length > 0 ? arr[0].timestamp : 0
})

const maxTime = computed(() => {
  const arr = cumulativeTimeline.value
  return arr.length > 0 ? arr[arr.length - 1].timestamp : 0
})

// SVG chart dimensions & coordinates
const W = 1000
const H = 200
const PAD_LEFT = 40
const PAD_RIGHT = 15
const PAD_TOP = 15
const PAD_BOTTOM = 0

// Calculate X coordinate proportionally based on date timestamp
const toX = (timestamp: number) => {
  const min = minTime.value
  const max = maxTime.value
  if (max === min) {
    return (PAD_LEFT + (W - PAD_RIGHT)) / 2
  }
  const ratio = (timestamp - min) / (max - min)
  return PAD_LEFT + ratio * (W - PAD_LEFT - PAD_RIGHT)
}

const toXPercent = (timestamp: number) => {
  const min = minTime.value
  const max = maxTime.value
  if (max === min) return 50
  const x = toX(timestamp)
  return (x / W) * 100
}

const toY = (val: number) =>
  PAD_TOP + (1 - val / maxCount.value) * (H - PAD_TOP - PAD_BOTTOM)

const linePath = computed(() => {
  const data = cumulativeTimeline.value
  if (!data.length) return ''
  if (data.length === 1) {
    const y = toY(data[0].cumulative)
    return `M ${PAD_LEFT},${y} L ${W - PAD_RIGHT},${y}`
  }
  return data.map((item, i) => `${i === 0 ? 'M' : 'L'} ${toX(item.timestamp)},${toY(item.cumulative)}`).join(' ')
})

const areaPath = computed(() => {
  const data = cumulativeTimeline.value
  if (!data.length) return ''
  const baseline = H
  if (data.length === 1) {
    const y = toY(data[0].cumulative)
    return `M ${PAD_LEFT},${baseline} L ${PAD_LEFT},${y} L ${W - PAD_RIGHT},${y} L ${W - PAD_RIGHT},${baseline} Z`
  }
  const pts = data.map(item => `${toX(item.timestamp)},${toY(item.cumulative)}`).join(' L ')
  const last = toX(data[data.length - 1].timestamp)
  const first = toX(data[0].timestamp)
  return `M ${first},${baseline} L ${pts} L ${last},${baseline} Z`
})

// Y-axis grid lines — round numbers
const yTicks = computed(() => {
  const max = maxCount.value
  if (max === 0) return []
  const rawStep = max / 4
  const magnitude = Math.pow(10, Math.floor(Math.log10(rawStep || 1)))
  const step = Math.max(1, Math.ceil(rawStep / magnitude) * magnitude)
  const ticks = []
  for (let v = step; v <= max + step * 0.5; v += step) {
    if (v > max * 1.15) break
    ticks.push(v)
  }
  return ticks.length ? ticks : [max]
})

// Responsive X-axis labels: evenly spaced to prevent overlap
const visibleXLabels = computed(() => {
  const data = cumulativeTimeline.value
  if (!data.length) return []
  if (data.length <= 1) return data

  const result: StarPoint[] = [data[0]]
  let lastX = toX(data[0].timestamp)
  const minSpacing = 85
  const endX = toX(data[data.length - 1].timestamp)

  for (let i = 1; i < data.length - 1; i++) {
    const currentX = toX(data[i].timestamp)
    if (currentX - lastX >= minSpacing && endX - currentX >= minSpacing) {
      result.push(data[i])
      lastX = currentX
    }
  }

  result.push(data[data.length - 1])
  return result
})

// Hit area boundary calculations
const getRectX = (index: number) => {
  const data = cumulativeTimeline.value
  if (data.length <= 1 || index === 0) return 0
  return (toX(data[index - 1].timestamp) + toX(data[index].timestamp)) / 2
}

const getRectWidth = (index: number) => {
  const data = cumulativeTimeline.value
  if (data.length <= 1) return W
  const start = getRectX(index)
  const end = index === data.length - 1
    ? W
    : (toX(data[index].timestamp) + toX(data[index + 1].timestamp)) / 2
  return end - start
}

// Hover state
const hoverIndex = ref<number | null>(null)
const hoveredItem = computed(() =>
  hoverIndex.value !== null ? cumulativeTimeline.value[hoverIndex.value] : null
)
</script>

<template>
  <Card class="rounded-xl border border-border bg-card p-5 shadow-sm">
    <!-- Header -->
    <CardHeader class="p-0 pb-4 flex flex-row items-center justify-between space-y-0">
      <div class="flex items-center gap-2">
        <Star class="size-4 text-amber-500 fill-amber-400" />
        <CardTitle class="text-sm font-semibold text-foreground">Star History</CardTitle>
      </div>
      <div class="text-right">
        <div class="text-2xl font-bold text-foreground tabular-nums">
          {{ totalStars.toLocaleString() }}
        </div>
        <CardDescription class="text-xs text-muted-foreground">total stars</CardDescription>
      </div>
    </CardHeader>

    <CardContent class="p-0">
      <!-- Loading State -->
      <div v-if="isLoading" class="h-48 animate-pulse bg-muted rounded-lg" />

      <!-- Error State -->
      <div v-else-if="error" class="h-48 flex items-center justify-center text-muted-foreground text-sm">
        {{ error }}
      </div>

      <!-- Empty State -->
      <div
        v-else-if="cumulativeTimeline.length === 0"
        class="h-48 flex flex-col items-center justify-center gap-2 text-muted-foreground text-sm"
      >
        <Star class="size-8 stroke-[1.5] text-muted-foreground/30" />
        No stars yet. Be the first!
      </div>

      <!-- Full View Chart -->
      <div v-else class="relative w-full">
        <!-- Tooltip Pill (fixed at top to prevent layout shift) -->
        <div class="h-8 mb-1 flex items-center justify-center">
          <Transition
            enter-from-class="opacity-0 scale-95"
            enter-active-class="transition-all duration-150"
            leave-to-class="opacity-0 scale-95"
            leave-active-class="transition-all duration-100"
          >
            <div
              v-if="hoveredItem"
              class="flex items-center gap-2 text-xs bg-popover text-popover-foreground border border-border rounded-md px-3 py-1.5 shadow-md"
            >
              <span class="text-muted-foreground">{{ hoveredItem.fullLabel }}</span>
              <span class="w-px h-3 bg-border" />
              <span class="font-semibold text-amber-500">{{ hoveredItem.cumulative.toLocaleString() }} stars</span>
              <span class="text-muted-foreground">(+{{ hoveredItem.count }})</span>
            </div>
            <div v-else class="text-xs text-muted-foreground/60">Hover over the chart to see details</div>
          </Transition>
        </div>

        <!-- SVG Timeline Chart across full width -->
        <div class="relative w-full">
          <svg
            :viewBox="`0 0 ${W} ${H}`"
            preserveAspectRatio="none"
            class="w-full"
            style="height: 160px"
            @mouseleave="hoverIndex = null"
          >
            <defs>
              <linearGradient id="star-area-grad" x1="0" y1="0" x2="0" y2="1">
                <stop offset="0%" stop-color="rgb(245,158,11)" stop-opacity="0.25" />
                <stop offset="100%" stop-color="rgb(245,158,11)" stop-opacity="0.02" />
              </linearGradient>
            </defs>

            <!-- Y-axis grid lines -->
            <g>
              <line
                v-for="tick in yTicks"
                :key="tick"
                :x1="PAD_LEFT"
                :x2="W - PAD_RIGHT"
                :y1="toY(tick)"
                :y2="toY(tick)"
                stroke="currentColor"
                stroke-width="0.5"
                stroke-dasharray="4 4"
                class="text-border"
              />
            </g>

            <!-- Y-axis labels -->
            <g>
              <text
                v-for="tick in yTicks"
                :key="`label-${tick}`"
                :x="PAD_LEFT - 6"
                :y="toY(tick)"
                text-anchor="end"
                dominant-baseline="middle"
                font-size="13"
                class="fill-muted-foreground font-mono"
              >
                {{ tick >= 1000 ? `${(tick / 1000).toFixed(1)}k` : tick }}
              </text>
            </g>

            <!-- Area fill -->
            <path :d="areaPath" fill="url(#star-area-grad)" />

            <!-- Main Trend Line -->
            <path
              :d="linePath"
              fill="none"
              stroke="rgb(245,158,11)"
              stroke-width="2.5"
              stroke-linejoin="round"
              stroke-linecap="round"
            />

            <!-- Point dots for small datasets -->
            <g v-if="cumulativeTimeline.length <= 15">
              <circle
                v-for="item in cumulativeTimeline"
                :key="`dot-${item.date}`"
                :cx="cumulativeTimeline.length === 1 ? (PAD_LEFT + (W - PAD_RIGHT)) / 2 : toX(item.timestamp)"
                :cy="toY(item.cumulative)"
                r="4"
                fill="rgb(245,158,11)"
                stroke="white"
                stroke-width="1.5"
              />
            </g>

            <!-- Hover crosshair & point -->
            <g v-if="hoverIndex !== null && hoveredItem">
              <line
                :x1="cumulativeTimeline.length === 1 ? (PAD_LEFT + (W - PAD_RIGHT)) / 2 : toX(hoveredItem.timestamp)"
                :x2="cumulativeTimeline.length === 1 ? (PAD_LEFT + (W - PAD_RIGHT)) / 2 : toX(hoveredItem.timestamp)"
                :y1="PAD_TOP"
                :y2="H"
                stroke="rgb(245,158,11)"
                stroke-width="1"
                stroke-dasharray="3 3"
                opacity="0.6"
              />
              <circle
                :cx="cumulativeTimeline.length === 1 ? (PAD_LEFT + (W - PAD_RIGHT)) / 2 : toX(hoveredItem.timestamp)"
                :cy="toY(hoveredItem.cumulative)"
                r="5"
                fill="rgb(245,158,11)"
                stroke="white"
                stroke-width="2"
              />
            </g>

            <!-- Hit areas for hover detection -->
            <rect
              v-for="(item, index) in cumulativeTimeline"
              :key="item.date"
              :x="getRectX(index)"
              :width="getRectWidth(index)"
              y="0"
              :height="H"
              fill="transparent"
              class="cursor-crosshair"
              @mouseenter="hoverIndex = index"
            />
          </svg>

          <!-- X-axis labels (below SVG) positioned by timestamp -->
          <div class="relative mt-1" style="height: 20px">
            <span
              v-for="item in visibleXLabels"
              :key="item.date"
              class="absolute text-[11px] text-muted-foreground -translate-x-1/2 whitespace-nowrap"
              :style="{
                left: cumulativeTimeline.length === 1
                  ? '50%'
                  : `${toXPercent(item.timestamp)}%`
              }"
            >
              {{ item.label }}
            </span>
          </div>
        </div>
      </div>
    </CardContent>
  </Card>
</template>
