<script setup lang="ts">
import { computed } from 'vue'
import { Clock, CircleAlert } from 'lucide-vue-next'
import { PluginStatus } from '@/api/generated/model'
import type { PluginResource } from '@/api/generated/model'
import { formatRelativeDate } from '@/utils/date'

const props = defineProps<{
  plugin: PluginResource
}>()

type Notice = {
  icon: typeof Clock
  title: string
  body: string
  class: string
}


const NOTICES: Record<string, Notice> = {
  [PluginStatus.pending]: {
    icon: Clock,
    title: 'Waiting for review',
    body: 'It will appear in the catalogue once an admin approves it.',
    class: 'border-amber-500/20 bg-amber-500/5 text-amber-700 dark:text-amber-400',
  },
  [PluginStatus.rejected]: {
    icon: CircleAlert,
    title: 'Rejected',
    body: 'An admin rejected this submission, so it cannot be installed from the registry.',
    class: 'border-destructive/20 bg-destructive/5 text-destructive',
  },
}

const notice = computed<Notice | undefined>(() => NOTICES[props.plugin.status])
</script>

<template>
  <div
    v-if="notice"
    class="flex gap-3 rounded-xl border p-4 text-sm"
    :class="notice.class"
  >
    <component :is="notice.icon" class="mt-0.5 size-4 shrink-0" />

    <div class="space-y-1">
      <p class="font-semibold">{{ notice.title }}</p>
      <p class="opacity-90">
        Submitted <time :datetime="plugin.created_at ?? undefined">{{ formatRelativeDate(plugin.created_at) }}</time>.
        {{ notice.body }}
      </p>
    </div>
  </div>
</template>
