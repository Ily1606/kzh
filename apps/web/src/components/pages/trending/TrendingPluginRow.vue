<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { Eye, Star, MessageSquare, ExternalLink, Calendar } from '@lucide/vue'
import type { PluginResource } from '@/api/generated/model'
import Author from '@/components/common/Author.vue'
import PluginStarButton from '@/components/pages/plugin-detail/PluginStarButton.vue'
import { formatRelativeDate } from '@/utils/date'

const props = defineProps<{
  plugin: PluginResource
  rank: number
}>()

const route = useRoute()
const authorName = computed(() => props.plugin.author?.name || 'community')
const authorAvatar = computed(() => props.plugin.author?.avatar_url ?? null)
const submittedDate = computed(() => props.plugin.approved_at ?? props.plugin.created_at)
</script>

<template>
  <article
    class="group relative flex flex-col gap-2 px-4 py-4.5 sm:px-6 sm:py-5 transition-colors hover:bg-muted/50 focus-within:bg-muted/50"
    :data-plugin-id="plugin.id"
  >
    <!-- Top row: Rank, Author / Plugin Name (GitHub Style), and Star Button -->
    <div class="flex items-start justify-between gap-3">
      <div class="flex items-center gap-2 min-w-0 flex-1 flex-wrap">
        <!-- Rank Badge -->
        <span
          class="inline-flex size-6 shrink-0 items-center justify-center rounded-full font-mono text-xs font-normal ring-1 ring-inset"
          :class="{
            'bg-amber-100 text-amber-900 ring-amber-400 dark:bg-amber-400 dark:text-amber-950 dark:ring-amber-300': rank === 1,
            'bg-slate-200 text-slate-800 ring-slate-400 dark:bg-slate-200 dark:text-slate-900 dark:ring-slate-100': rank === 2,
            'bg-orange-100 text-orange-900 ring-orange-400 dark:bg-orange-400 dark:text-orange-950 dark:ring-orange-300': rank === 3,
            'bg-muted text-muted-foreground ring-zinc-300 dark:bg-zinc-800 dark:text-zinc-300 dark:ring-zinc-700': rank > 3,
          }"
        >
          {{ rank }}
        </span>

        <!-- Author / Plugin Name -->
        <h2 class="text-base sm:text-lg font-normal tracking-tight min-w-0 font-mono">
          <RouterLink
            :to="{ name: 'plugin-detail', params: { id: plugin.id }, query: { redirect: route.fullPath } }"
            class="inline-flex items-baseline gap-1 text-foreground"
          >
            <span class="font-normal text-foreground group-hover:text-primary">{{ authorName }}</span>
            <span class="text-foreground font-normal group-hover:text-primary">/</span>
            <span class="font-normal text-foreground group-hover:text-primary">{{ plugin.name }}</span>
          </RouterLink>
        </h2>
      </div>

      <!-- Star Button inside the row -->
      <div class="shrink-0">
        <PluginStarButton :plugin="plugin" size="sm" :full-width="false" />
      </div>
    </div>

    <!-- Description / Title -->
    <p
      v-if="plugin.title"
      class="text-xs sm:text-sm font-normal text-muted-foreground leading-normal line-clamp-2 max-w-3xl"
    >
      {{ plugin.title }}
    </p>

    <!-- Meta row: Author, Stars, Views, Comments, Source, Date -->
    <div class="mt-1 flex flex-wrap items-center gap-x-4.5 gap-y-1.5 text-xs text-muted-foreground">
      <!-- Author -->
      <span class="inline-flex items-center gap-1.5 text-foreground/90 font-normal">
        <span class="text-muted-foreground font-normal">Built by</span>
        <Author :name="authorName" :src="authorAvatar" size="sm" class="font-normal text-foreground" />
      </span>

      <!-- Star Count -->
      <span
        class="inline-flex items-center gap-1 tabular-nums font-normal text-foreground/90"
        title="Total stars"
      >
        <Star class="size-3.5 text-foreground/70 shrink-0" />
        <span>{{ (plugin.star_count ?? 0).toLocaleString() }}</span>
      </span>

      <!-- View Count -->
      <span
        class="inline-flex items-center gap-1 tabular-nums font-normal text-foreground/90"
        title="Total views"
      >
        <Eye class="size-3.5 text-foreground/70 shrink-0" />
        <span>{{ (plugin.view_count ?? 0).toLocaleString() }}</span>
      </span>

      <!-- Comment Count -->
      <span
        v-if="plugin.comment_count !== undefined"
        class="hidden sm:inline-flex items-center gap-1 tabular-nums font-normal text-foreground/90"
        title="Total comments"
      >
        <MessageSquare class="size-3.5 text-foreground/70 shrink-0" />
        <span>{{ (plugin.comment_count ?? 0).toLocaleString() }}</span>
      </span>

      <!-- Source Link -->
      <a
        v-if="plugin.source_link"
        :href="plugin.source_link"
        target="_blank"
        rel="noopener noreferrer"
        class="hidden sm:inline-flex items-center gap-1 font-normal text-foreground/90 hover:text-primary transition-colors hover:underline"
        title="Source code"
      >
        <ExternalLink class="size-3 shrink-0" />
        <span>Source</span>
      </a>

      <!-- Date -->
      <time
        v-if="submittedDate"
        :datetime="submittedDate"
        class="hidden md:inline-flex items-center gap-1 font-normal text-muted-foreground ml-auto"
      >
        <Calendar class="size-3 shrink-0" />
        <span>Published {{ formatRelativeDate(submittedDate) }}</span>
      </time>
    </div>
  </article>
</template>
