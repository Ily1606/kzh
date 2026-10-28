<script setup lang="ts">
import { ref, toRef } from 'vue'
import { useRouter, useRoute, RouterLink } from 'vue-router'
import { ArrowUpRight, BookOpen, Calendar, Eye, Flag, Folder, History, MoreVertical, Scale, Star, Trash2 } from '@lucide/vue'
import type { PluginResource } from '@/api/generated/model'
import { formatRelativeDate } from '@/utils/date'
import { safeRedirect } from '@/utils/safe-redirect'
import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { usePluginDetailAccess } from '@/composables/usePluginDetailAccess'
import { usePluginsStore } from '@/stores'
import { notifySuccess } from '@/utils/toast'
import PluginStatusBadge from '@/components/pages/plugin/PluginStatusBadge.vue'
import InstallCommand from './InstallCommand.vue'
import PluginOwnerActions from './PluginOwnerActions.vue'
import PluginStarButton from './PluginStarButton.vue'
import PluginReportModal from './PluginReportModal.vue'
import PluginDeleteModal from './PluginDeleteModal.vue'

const props = defineProps<{
  plugin: PluginResource
}>()

const router = useRouter()
const route = useRoute()
const store = usePluginsStore()

const showReportModal = ref(false)
const showDeleteModal = ref(false)
const isDeleting = ref(false)
const isMenuOpen = ref(false)

const { showStatusBadge, showStar, showInstallCommand, showHistoryLink, isOwner, canDelete } = usePluginDetailAccess(
  toRef(props, 'plugin'),
)

function openReport() {
  isMenuOpen.value = false
  showReportModal.value = true
}

function openDelete() {
  isMenuOpen.value = false
  showDeleteModal.value = true
}

async function handleDelete() {
  if (isDeleting.value) return
  isDeleting.value = true

  try {
    const success = await store.deletePlugin(props.plugin.id)
    if (success) {
      showDeleteModal.value = false
      notifySuccess('Plugin deleted successfully.')

      const rawRedirect = Array.isArray(route.query.redirect)
        ? route.query.redirect[0]
        : route.query.redirect
      const redirect = safeRedirect(rawRedirect, '')

      if (redirect) {
        await router.push(redirect)
      } else {
        await router.push({ name: 'my-plugins' })
      }
    }
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <div class="flex flex-col justify-between gap-6 border-b pb-8 md:flex-row md:items-start">
    <div class="space-y-3">
      <div class="flex flex-wrap items-center gap-3">
        <h1 class="text-3xl font-bold tracking-tight">{{ plugin.name }}</h1>
        <PluginStatusBadge v-if="showStatusBadge" :status="plugin.status" />

        <!-- 3-dot dropdown menu to the right of the plugin name -->
        <Popover v-model:open="isMenuOpen">
          <PopoverTrigger as-child>
            <Button
              variant="ghost"
              size="icon"
              class="size-8 rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground cursor-pointer"
              title="More options"
              aria-label="More options"
            >
              <MoreVertical class="size-4.5" />
            </Button>
          </PopoverTrigger>

          <PopoverContent v-if="isMenuOpen" align="start" class="w-36 p-1">
            <div class="flex flex-col gap-0.5">
              <!-- Report action -->
              <button
                v-if="!isOwner"
                type="button"
                class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-sm font-medium text-foreground hover:bg-muted cursor-pointer transition-colors"
                @click="openReport"
              >
                <Flag class="size-4 text-muted-foreground" />
                <span>Report</span>
              </button>

              <!-- Delete action -->
              <button
                v-if="canDelete"
                type="button"
                class="flex w-full items-center gap-2 rounded-md px-2.5 py-1.5 text-sm font-medium text-destructive hover:bg-destructive/10 cursor-pointer transition-colors"
                @click="openDelete"
              >
                <Trash2 class="size-4" />
                <span>Delete</span>
              </button>
            </div>
          </PopoverContent>
        </Popover>
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
        <span v-if="plugin.category" class="flex items-center gap-x-2">
          <Folder class="size-5 text-muted-foreground" />
          <span>Category: <span class="font-medium text-foreground">{{ plugin.category }}</span></span>
        </span>
      </div>

      <!-- Tags list -->
      <div v-if="plugin.tags?.length" class="flex flex-wrap items-center gap-1.5 pt-1">
        <span
          v-for="tag in plugin.tags"
          :key="tag"
          class="rounded-full bg-secondary/80 border border-border/60 px-2.5 py-0.5 text-xs font-medium text-secondary-foreground"
        >
          #{{ tag }}
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

      <div class="flex flex-wrap items-center gap-2">
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

  <PluginReportModal
    :show="showReportModal"
    :plugin-id="plugin.id"
    :plugin-name="plugin.name"
    @close="showReportModal = false"
  />

  <PluginDeleteModal
    :show="showDeleteModal"
    :plugin-name="plugin.name"
    :is-deleting="isDeleting"
    @close="showDeleteModal = false"
    @confirm="handleDelete"
  />
</template>
