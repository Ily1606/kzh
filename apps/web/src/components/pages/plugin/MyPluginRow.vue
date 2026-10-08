<script setup lang="ts">
import { Boxes, Eye, MessageSquare, Star } from 'lucide-vue-next'
import type { PluginResource } from '@/api/generated/model'
import { formatRelativeDate } from '@/utils/date'
import PluginStatusBadge from './PluginStatusBadge.vue'

defineProps<{
  plugin: PluginResource
}>()
</script>

<template>
  <li class="border-b last:border-b-0">
    <div
      class="group flex items-center gap-4 px-4 py-3 transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-inset focus-visible:outline-none"
    >
      <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
        <Boxes class="size-4" />
      </span>

      <span class="min-w-0 flex-1">
        <span class="block truncate font-mono text-sm font-medium text-foreground transition-colors group-hover:text-primary">
          {{ plugin.name }}
        </span>
        <span class="block truncate text-xs text-muted-foreground">{{ plugin.title }}</span>
      </span>

      <PluginStatusBadge :status="plugin.status" />

      <span class="hidden shrink-0 items-center gap-4 text-xs text-muted-foreground sm:flex">
        <span class="flex w-12 items-center justify-end gap-1 tabular-nums">
          <Eye class="size-3.5 shrink-0" />
          <span class="sr-only">Views</span>
          {{ plugin.view_count }}
        </span>
        <span class="flex w-12 items-center justify-end gap-1 tabular-nums">
          <Star class="size-3.5 shrink-0" />
          <span class="sr-only">Stars</span>
          {{ plugin.star_count }}
        </span>
        <span class="flex w-12 items-center justify-end gap-1 tabular-nums">
          <MessageSquare class="size-3.5 shrink-0" />
          <span class="sr-only">Comments</span>
          {{ plugin.comment_count }}
        </span>
      </span>

      <time
        :datetime="plugin.approved_at ?? undefined"
        class="hidden w-24 shrink-0 text-right text-xs text-muted-foreground md:block"
      >
        {{ formatRelativeDate(plugin.approved_at) }}
      </time>
    </div>
  </li>
</template>
