<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Boxes, Eye, Star, MessageSquare } from '@lucide/vue'
import type { PluginResource } from '@/api/generated/model'
import Author from '@/components/common/Author.vue'
import PluginStatusBadge from '@/components/pages/plugin/PluginStatusBadge.vue'
import { formatRelativeDate } from '@/utils/date'

interface Props {
  plugin: PluginResource
  rank?: number
  showStatusBadge?: boolean
}

const props = defineProps<Props>()

const authorName = computed(() => props.plugin.author?.name || 'community')
const authorAvatar = computed(() => props.plugin.author?.avatar_url ?? null)
const submittedDate = computed(() => props.plugin.approved_at ?? props.plugin.created_at)
const hasAuthor = computed(() => !!props.plugin.author)

const rankClasses = computed(() => {
  const rank = props.rank
  if (!rank) return ''
  const base = 'inline-flex size-6 shrink-0 items-center justify-center rounded-full font-mono text-xs font-normal ring-1 ring-inset'
  if (rank === 1) return `${base} bg-amber-100 text-amber-900 ring-amber-400 dark:bg-amber-400 dark:text-amber-950 dark:ring-amber-300`
  if (rank === 2) return `${base} bg-slate-200 text-slate-800 ring-slate-400 dark:bg-slate-200 dark:text-slate-900 dark:ring-slate-100`
  if (rank === 3) return `${base} bg-orange-100 text-orange-900 ring-orange-400 dark:bg-orange-400 dark:text-orange-950 dark:ring-orange-300`
  return `${base} bg-muted text-muted-foreground ring-zinc-300 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700`
})

const rankLabel = computed(() => props.rank ? `${props.rank}` : '')

const stats = computed(() => [
  { icon: Star, label: 'Stars', value: props.plugin.star_count },
  { icon: Eye, label: 'Views', value: props.plugin.view_count },
  { icon: MessageSquare, label: 'Comments', value: props.plugin.comment_count },
])
</script>

<template>
  <li class="border-b last:border-b-0" :data-plugin-id="plugin.id">
    <component
      :is="hasAuthor ? RouterLink : 'div'"
      :to="hasAuthor ? { name: 'plugin-detail', params: { username: plugin.author!.username, pluginName: plugin.name } } : undefined"
      class="group flex items-center gap-4 px-4 py-3"
      :class="hasAuthor
        ? 'transition-colors hover:bg-muted/50 focus-visible:ring-2 focus-visible:ring-ring/50 focus-visible:ring-inset focus-visible:outline-none'
        : 'text-muted-foreground'"
    >
      <!-- Left zone: Rank badge OR Status badge OR Plugin icon -->
      <div class="flex items-center gap-2 shrink-0">
        <!-- Rank badge (Trending) -->
        <span v-if="rank" :class="rankClasses">{{ rankLabel }}</span>
        <!-- Status badge (MyPlugins) -->
        <PluginStatusBadge v-else-if="showStatusBadge" :status="plugin.status" />
        <!-- Default plugin icon (Profile) -->
        <span v-else class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary/10 text-primary">
          <Boxes class="size-4" />
        </span>
      </div>

      <!-- Name & Title -->
      <span class="min-w-0 flex-1">
        <span
          class="block truncate font-mono text-sm font-medium text-foreground"
          :class="hasAuthor ? 'transition-colors group-hover:text-primary' : ''"
        >
          {{ plugin.name }}
        </span>
        <span class="block truncate max-w-xs text-xs text-muted-foreground">{{ plugin.title }}</span>
      </span>

      <!-- Meta row: Author + Stats + Date -->
      <div class="hidden shrink-0 items-center gap-4 text-xs text-muted-foreground sm:flex">
        <!-- Author with avatar -->
        <Author
          :name="authorName"
          :src="authorAvatar"
          size="sm"
          class="mr-auto shrink-0"
        />

        <!-- Stats: Stars, Views, Comments -->
        <span
          v-for="s in stats"
          :key="s.label"
          class="flex w-12 items-center justify-end gap-1 tabular-nums"
        >
          <component :is="s.icon" class="size-3.5 shrink-0" />
          <span class="sr-only">{{ s.label }}</span>
          {{ (s.value ?? 0).toLocaleString() }}
        </span>

        <!-- Date -->
        <time
          :datetime="submittedDate ?? undefined"
          class="hidden w-24 shrink-0 text-right text-xs text-muted-foreground md:block first-letter:uppercase"
        >
          {{ formatRelativeDate(submittedDate) }}
        </time>
      </div>
    </component>
  </li>
</template>
