<script setup lang="ts">
import { toRef } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowUpRight, BookOpen, Calendar, Eye, History, Scale, Star } from 'lucide-vue-next'
import type { PluginResource } from '@/api/generated/model'
import { formatRelativeDate } from '@/utils/date'
import { usePluginDetailAccess } from '@/composables/usePluginDetailAccess'
import PluginStatusBadge from '@/components/pages/plugin/PluginStatusBadge.vue'
import InstallCommand from './InstallCommand.vue'
import PluginOwnerActions from './PluginOwnerActions.vue'
import PluginStarButton from './PluginStarButton.vue'

const props = defineProps<{
  plugin: PluginResource
}>()

const { showStatusBadge, showStar, showInstallCommand, showHistoryLink } = usePluginDetailAccess(
  toRef(props, 'plugin'),
)
</script>

<template>
  <div class="flex flex-col justify-between gap-6 border-b pb-8 md:flex-row md:items-start">
    <div class="space-y-3">
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-bold tracking-tight">{{ plugin.name }}</h1>
        <PluginStatusBadge v-if="showStatusBadge" :status="plugin.status" />
      </div>

      <p class="text-lg text-muted-foreground">{{ plugin.title }}</p>

       <!-- Infomation: Author, License, updated_at, views, stars -->
      <div class="@container grid grid-cols-1 items-center gap-y-2 gap-x-4 text-sm text-muted-foreground pt-1">
        <span class="flex items-center gap-x-2">
          <BookOpen class="size-5 text-muted-foreground" />
          <span>By: <span class="font-medium text-foreground">{{ plugin.author?.name }}</span></span>
        </span>
        <span class="flex items-center gap-x-2">
          <Scale class="size-5" />
          <span>{{ plugin.license }} license</span>
        </span>
        <span class="flex items-center gap-x-2">
          <Calendar class="size-5" />
          <span>{{ formatRelativeDate(plugin.updated_at) }}</span>
        </span>
        <span class="flex items-center gap-x-2">
          <Eye class="size-4 text-muted-foreground" />
          <span>{{ plugin.view_count || 0 }}</span> views
        </span>
        <span class="flex items-center gap-x-2">
          <Star class="size-4 text-muted-foreground" />
          <span>{{ plugin.star_count || 0 }}</span> stars
        </span>
      </div>

      <!-- Owner-only. -->
      <RouterLink
        v-if="showHistoryLink"
        :to="{ name: 'plugin-history', params: { id: plugin.id } }"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
      >
        <History class="size-4" />
        View review history
      </RouterLink>
    </div>

    <div class="flex min-w-65 flex-col gap-3">
      <InstallCommand v-if="showInstallCommand" :name="plugin.name" />

      <div class="flex items-center gap-2">
        <PluginStarButton v-if="showStar" :plugin="plugin" />

        <PluginOwnerActions :plugin="plugin" />

        <!-- View source button -->
        <a
          :href="plugin.source_link"
          target="_blank"
          rel="noopener noreferrer"
          class="inline-flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm transition-colors hover:bg-primary/90"
        >
          Source <ArrowUpRight class="size-4" />
        </a>
      </div>
    </div>
  </div>
</template>
