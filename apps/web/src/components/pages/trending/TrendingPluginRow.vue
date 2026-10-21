<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { Eye, Star, MessageSquare, ExternalLink, Calendar } from '@lucide/vue'
import type { PluginResource } from '@/api/generated/model'
import Author from '@/components/common/Author.vue'
import PluginStarButton from '@/components/pages/plugin-detail/PluginStarButton.vue'
import { formatRelativeDate } from '@/utils/date'

const props = defineProps<{
  plugin: PluginResource
  rank: number
}>()

const authorName = computed(() => props.plugin.author?.name || 'community')
const authorAvatar = computed(() => props.plugin.author?.avatar_url ?? null)
const submittedDate = computed(() => props.plugin.approved_at ?? props.plugin.created_at)
</script>

<template>
  <article
    class="group relative flex flex-col gap-3 p-5 sm:p-6 transition-colors hover:bg-muted/40 focus-within:bg-muted/40"
    :data-plugin-id="plugin.id"
  >
    <!-- Top row: Rank, Author / Plugin Name (GitHub Style), and Star Button -->
    <div class="flex items-start justify-between gap-4">
      <div class="flex items-center gap-2.5 min-w-0 flex-1 flex-wrap">
        <!-- Rank Badge -->
        <span
          class="inline-flex size-6 shrink-0 items-center justify-center rounded-full font-mono text-xs font-bold ring-1 ring-inset"
          :class="{
            'bg-amber-100 text-amber-900 ring-amber-500/60 dark:bg-amber-950/80 dark:text-amber-200': rank === 1,
            'bg-slate-200 text-slate-900 ring-slate-400 dark:bg-slate-700 dark:text-slate-100': rank === 2,
            'bg-orange-100 text-orange-900 ring-orange-400 dark:bg-orange-950/80 dark:text-orange-200': rank === 3,
            'bg-muted text-foreground ring-border': rank > 3,
          }"
        >
          {{ rank }}
        </span>

        <!-- Author / Plugin Name -->
        <h2 class="text-base sm:text-lg font-bold tracking-tight min-w-0 font-mono">
          <RouterLink
            :to="{ name: 'plugin-detail', params: { id: plugin.id } }"
            class="inline-flex items-baseline gap-1 hover:underline text-foreground"
          >
            <span class="font-semibold text-foreground/85 group-hover:text-foreground">{{ authorName }}</span>
            <span class="text-foreground/40 font-normal">/</span>
            <span class="font-extrabold text-foreground group-hover:text-primary underline-offset-4">{{ plugin.name }}</span>
          </RouterLink>
        </h2>
      </div>

      <!-- Star Button inside the row -->
      <div class="shrink-0">
        <PluginStarButton :plugin="plugin" size="sm" :full-width="false" />
      </div>
    </div>

    <!-- Description / Title: Darker and bolder font -->
    <p
      v-if="plugin.title"
      class="text-sm font-medium text-foreground/90 leading-relaxed line-clamp-2 max-w-3xl"
    >
      {{ plugin.title }}
    </p>

    <!-- Meta row: Category, Author, Stars, Views, Comments, Source, Date -->
    <div class="mt-1 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs font-medium text-foreground/85">
      <!-- Category Badge -->
      <span v-if="plugin.category" class="inline-flex items-center gap-1.5 font-bold text-foreground">
        <span class="size-2 rounded-full bg-primary shrink-0" />
        <span>{{ plugin.category }}</span>
      </span>

      <!-- Author -->
      <span class="inline-flex items-center gap-1.5 text-foreground">
        <span class="text-foreground/70 font-normal">Built by</span>
        <Author :name="authorName" :src="authorAvatar" size="sm" class="font-bold text-foreground" />
      </span>

      <!-- Star Count -->
      <span
        class="inline-flex items-center gap-1 tabular-nums font-bold text-foreground"
        title="Total stars"
      >
        <Star class="size-3.5 text-amber-500 fill-amber-500/20 shrink-0" />
        <span>{{ (plugin.star_count ?? 0).toLocaleString() }}</span>
      </span>

      <!-- View Count -->
      <span
        class="inline-flex items-center gap-1 tabular-nums font-bold text-foreground"
        title="Total views"
      >
        <Eye class="size-3.5 text-foreground/80 shrink-0" />
        <span>{{ (plugin.view_count ?? 0).toLocaleString() }}</span>
      </span>

      <!-- Comment Count -->
      <span
        v-if="plugin.comment_count !== undefined"
        class="hidden sm:inline-flex items-center gap-1 tabular-nums font-bold text-foreground"
        title="Total comments"
      >
        <MessageSquare class="size-3.5 text-foreground/80 shrink-0" />
        <span>{{ (plugin.comment_count ?? 0).toLocaleString() }}</span>
      </span>

      <!-- Source Link -->
      <a
        v-if="plugin.source_link"
        :href="plugin.source_link"
        target="_blank"
        rel="noopener noreferrer"
        class="hidden sm:inline-flex items-center gap-1 font-bold text-foreground hover:text-primary transition-colors hover:underline"
        title="Source code"
      >
        <ExternalLink class="size-3 shrink-0" />
        <span>Source</span>
      </a>

      <!-- Date -->
      <time
        v-if="submittedDate"
        :datetime="submittedDate"
        class="hidden md:inline-flex items-center gap-1 font-medium text-foreground/80 ml-auto"
      >
        <Calendar class="size-3 shrink-0" />
        <span>Updated {{ formatRelativeDate(submittedDate) }}</span>
      </time>
    </div>
  </article>
</template>
