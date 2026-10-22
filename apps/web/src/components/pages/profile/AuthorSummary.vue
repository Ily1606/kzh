<script setup lang="ts">
import { Calendar, Link, Package, UserRound } from '@lucide/vue'
import type { AuthorResource } from '@/api/generated/model'
import { formatDate } from '@/utils/date'

defineProps<{
  author: AuthorResource
  pluginsCount: number
}>()
</script>

<template>
  <aside class="flex flex-col items-center gap-4 text-center md:sticky md:top-24 md:items-start md:text-left">
    <span class="grid size-24 shrink-0 place-items-center overflow-hidden rounded-full bg-primary/10 text-primary ring-1 ring-border">
      <img
        v-if="author.avatar_url"
        :src="author.avatar_url"
        :alt="author.name"
        class="size-full object-cover"
      />
      <UserRound v-else class="size-12" />
    </span>

    <div class="min-w-0">
      <h1 class="truncate text-2xl font-bold tracking-tight">{{ author.name }}</h1>
      <p class="text-sm text-muted-foreground">@{{ author.username }}</p>
    </div>

    <a
      v-if="author.github_link"
      :href="author.github_link"
      target="_blank"
      rel="noopener noreferrer"
      class="inline-flex max-w-full items-center gap-2 text-sm font-medium text-foreground transition-colors hover:text-primary"
    >
      <Link class="size-4 shrink-0" />
      <span class="truncate">{{ author.github_link }}</span>
    </a>

    <dl class="flex flex-col gap-1.5 text-sm text-muted-foreground">
      <div class="flex items-center gap-2">
        <Package class="size-4 shrink-0" />
        <dd>
          <span class="font-medium text-foreground">{{ pluginsCount }}</span>
          published {{ pluginsCount === 1 ? 'plugin' : 'plugins' }}
        </dd>
      </div>
      <div class="flex items-center gap-2">
        <Calendar class="size-4 shrink-0" />
        <dd>Joined {{ formatDate(author.created_at) }}</dd>
      </div>
    </dl>
  </aside>
</template>
