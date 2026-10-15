<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue'
import { getStar } from '@/api/generated/endpoints/star/star'
import { Star } from 'lucide-vue-next'

const props = defineProps<{
  pluginId: string
}>()

interface TimelineData {
  date: string
  count: number
}

const timeline = ref<TimelineData[]>([])
const isLoading = ref(true)
const error = ref('')

const fetchTimeline = async () => {
  if (!props.pluginId) return
  isLoading.value = true
  error.value = ''
  try {
    const res = await getStar().starTimeline(props.pluginId)
    timeline.value = (res as any).data || res || []
  } catch {
    error.value = 'Failed to load timeline data'
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchTimeline)
watch(() => props.pluginId, fetchTimeline)

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

// Cumulative data
const cumulativeTimeline = computed(() => {
  let total = 0
  return timeline.value
    .filter(item => item.date)
    .map(item => {
      total += item.count
      return {
        date: item.date,
        count: item.count,
        cumulative: total,
        label: formatLabel(item.date),
        fullLabel: formatFull(item.date),
      }
    })
})

const totalStars = computed(() => {
  const arr = cumulativeTimeline.value
  return arr.length > 0 ? arr[arr.length - 1].cumulative : 0
})
const maxCount = computed(() => totalStars.value || 1)

// SVG chart coords (viewBox 0 0 1000 200)
const W = 1000
const H = 200
const PAD_LEFT = 40
const PAD_RIGHT = 10
const PAD_TOP = 12
const PAD_BOTTOM = 0

const toX = (index: number, total: number) =>
  PAD_LEFT + (index / Math.max(1, total - 1)) * (W - PAD_LEFT - PAD_RIGHT)

const toY = (val: number) =>
  PAD_TOP + (1 - val / maxCount.value) * (H - PAD_TOP - PAD_BOTTOM)

const linePath = computed(() => {
  const data = cumulativeTimeline.value
  if (!data.length) return ''
  if (data.length === 1) {
    const y = toY(data[0].cumulative)
    return `M ${PAD_LEFT},${y} L ${W - PAD_RIGHT},${y}`
  }
  return data.map((item, i) => `${i === 0 ? 'M' : 'L'} ${toX(i, data.length)},${toY(item.cumulative)}`).join(' ')
})

const areaPath = computed(() => {
  const data = cumulativeTimeline.value
  if (!data.length) return ''
  const baseline = H
  if (data.length === 1) {
    const y = toY(data[0].cumulative)
    return `M ${PAD_LEFT},${baseline} L ${PAD_LEFT},${y} L ${W - PAD_RIGHT},${y} L ${W - PAD_RIGHT},${baseline} Z`
  }
  const pts = data.map((item, i) => `${toX(i, data.length)},${toY(item.cumulative)}`).join(' L ')
  const last = toX(data.length - 1, data.length)
  const first = toX(0, data.length)
  return `M ${first},${baseline} L ${pts} L ${last},${baseline} Z`
})

// Y-axis grid lines — nice round numbers
const yTicks = computed(() => {
  const max = maxCount.value
  if (max === 0) return []
  const rawStep = max / 4
  const magnitude = Math.pow(10, Math.floor(Math.log10(rawStep)))
  const step = Math.ceil(rawStep / magnitude) * magnitude
  const ticks = []
  for (let v = step; v <= max + step * 0.5; v += step) {
    if (v > max * 1.1) break
    ticks.push(v)
  }
  return ticks
})

// Which X-axis labels to show
const labelInterval = computed(() => {
  const n = cumulativeTimeline.value.length
  if (n <= 10) return 1
  if (n <= 20) return 2
  if (n <= 40) return 5
  if (n <= 90) return 10
  return Math.ceil(n / 9)
})

// Whether to show the label at a given index
// Always show first, show others if on interval tick,
// and only show last if it doesn't crowd the previous tick.
const shouldShowLabel = (index: number): boolean => {
  const n = cumulativeTimeline.value.length
  const interval = labelInterval.value
  if (index === 0) return true
  if (index % interval === 0) return true
  if (index === n - 1) {
    // Find the previous tick that was shown
    const prevTick = Math.floor((n - 2) / interval) * interval
    // Only show last if it's at least half an interval away from the previous tick
    return (index - prevTick) >= Math.ceil(interval / 2)
  }
  return false
}

// Hover state
const hoverIndex = ref<number | null>(null)
const hoveredItem = computed(() =>
  hoverIndex.value !== null ? cumulativeTimeline.value[hoverIndex.value] : null
)
</script>

<template>
  <div class="rounded-xl border border-border bg-card p-5 shadow-sm">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <div class="flex items-center gap-2">
        <Star class="size-4 text-amber-500 fill-amber-400" />
        <span class="text-sm font-semibold text-foreground">Star History</span>
      </div>
      <div class="text-right">
        <div class="text-2xl font-bold text-foreground tabular-nums">
          {{ totalStars.toLocaleString() }}
        </div>
        <div class="text-xs text-muted-foreground">total stars</div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="isLoading" class="h-48 animate-pulse bg-muted rounded-lg" />

    <!-- Error -->
    <div v-else-if="error" class="h-48 flex items-center justify-center text-muted-foreground text-sm">
      {{ error }}
    </div>

    <!-- Empty -->
    <div v-else-if="cumulativeTimeline.length === 0" class="h-48 flex flex-col items-center justify-center gap-2 text-muted-foreground text-sm">
      <Star class="size-8 stroke-[1.5] text-muted-foreground/30" />
      No stars yet. Be the first!
    </div>

    <!-- Chart -->
    <div v-else class="relative">
      <!-- Tooltip (fixed at top so it doesn't interfere with layout) -->
      <div class="h-8 mb-1 flex items-center justify-center">
        <Transition enter-from-class="opacity-0 scale-95" enter-active-class="transition-all duration-150" leave-to-class="opacity-0 scale-95" leave-active-class="transition-all duration-100">
          <div v-if="hoveredItem" class="flex items-center gap-2 text-xs bg-popover text-popover-foreground border border-border rounded-md px-3 py-1.5 shadow-md">
            <span class="text-muted-foreground">{{ hoveredItem.fullLabel }}</span>
            <span class="w-px h-3 bg-border"></span>
            <span class="font-semibold text-amber-500">{{ hoveredItem.cumulative.toLocaleString() }} stars</span>
            <span class="text-muted-foreground">(+{{ hoveredItem.count }})</span>
          </div>
          <div v-else class="text-xs text-muted-foreground/50">Hover over the chart to see details</div>
        </Transition>
      </div>

      <!-- SVG chart -->
      <div class="relative">
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
              stroke-width="0.4"
              stroke-dasharray="4 4"
              class="text-border"
            />
          </g>

          <!-- Y-axis labels -->
          <g>
            <text
              v-for="tick in yTicks"
              :key="`label-${tick}`"
              :x="PAD_LEFT - 4"
              :y="toY(tick)"
              text-anchor="end"
              dominant-baseline="middle"
              font-size="14"
              class="fill-muted-foreground"
            >{{ tick >= 1000 ? `${(tick / 1000).toFixed(1)}k` : tick }}</text>
          </g>

          <!-- Area fill -->
          <path :d="areaPath" fill="url(#star-area-grad)" />

          <!-- Line -->
          <path
            :d="linePath"
            fill="none"
            stroke="rgb(245,158,11)"
            stroke-width="2.5"
            stroke-linejoin="round"
            stroke-linecap="round"
          />

          <!-- Hover crosshair & point -->
          <g v-if="hoverIndex !== null && hoveredItem">
            <line
              :x1="toX(hoverIndex, cumulativeTimeline.length)"
              :x2="toX(hoverIndex, cumulativeTimeline.length)"
              :y1="PAD_TOP"
              :y2="H"
              stroke="rgb(245,158,11)"
              stroke-width="1"
              stroke-dasharray="3 3"
              opacity="0.6"
            />
            <circle
              :cx="toX(hoverIndex, cumulativeTimeline.length)"
              :cy="toY(hoveredItem.cumulative)"
              r="5"
              fill="rgb(245,158,11)"
              stroke="white"
              stroke-width="1.5"
            />
          </g>

          <!-- Invisible hit areas for hover detection -->
          <rect
            v-for="(item, index) in cumulativeTimeline"
            :key="item.date"
            :x="index === 0 ? 0 : (toX(index, cumulativeTimeline.length) + toX(index - 1, cumulativeTimeline.length)) / 2"
            :width="cumulativeTimeline.length === 1
              ? W
              : index === 0
                ? (toX(1, cumulativeTimeline.length) + toX(0, cumulativeTimeline.length)) / 2
                : index === cumulativeTimeline.length - 1
                  ? W - (toX(index, cumulativeTimeline.length) + toX(index - 1, cumulativeTimeline.length)) / 2
                  : (toX(index + 1, cumulativeTimeline.length) - toX(index - 1, cumulativeTimeline.length)) / 2"
            y="0"
            :height="H"
            fill="transparent"
            class="cursor-crosshair"
            @mouseenter="hoverIndex = index"
          />
        </svg>

        <!-- X-axis labels (below SVG) -->
        <div class="relative mt-1" style="height: 20px">
          <span
            v-for="(item, index) in cumulativeTimeline"
            v-show="shouldShowLabel(index)"
            :key="item.date"
            class="absolute text-[10px] text-muted-foreground -translate-x-1/2 whitespace-nowrap"
            :style="{ left: `${((toX(index, cumulativeTimeline.length) - PAD_LEFT) / (W - PAD_LEFT - PAD_RIGHT)) * 100}%` }"
          >
            {{ item.label }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>
