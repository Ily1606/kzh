<script setup lang="ts">
import { computed, onMounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { usePluginsStore } from '@/stores'
import { usePluginDetailAccess } from '@/composables/usePluginDetailAccess'
import PluginComments from '@/components/pages/comment/PluginComments.vue'
import PluginDetailHeader from '@/components/pages/plugin-detail/PluginDetailHeader.vue'
import PluginReadme from '@/components/pages/plugin-detail/PluginReadme.vue'
import PluginStatusNotice from '@/components/pages/plugin-detail/PluginStatusNotice.vue'

const route = useRoute()
const store = usePluginsStore()

const pluginId = computed(() => route.params.id as string)
const plugin = computed(() => store.findById(pluginId.value))
const { showComments } = usePluginDetailAccess(plugin)

// When the URL contains a hash (eg: #comment, #...), it help scroll to the specific target location (eg: comment target)
async function scrollToHash() {
  await nextTick()

  if (route.hash) {
    // We add a tiny timeout because Markdown might contain images that cause reflows right after render
    setTimeout(() => {
      const el = document.querySelector(route.hash)
      if (el) el.scrollIntoView({ behavior: 'smooth' })
    }, 100)
  }
}

function onCommentAdded() {
  if (!plugin.value) return

  store.patchPlugin(plugin.value.id, {
    comment_count: (plugin.value.comment_count ?? 0) + 1,
  })
}

onMounted(async () => {
  await store.ensurePlugin(pluginId.value)
  scrollToHash()
})
</script>

<template>
  <div class="mx-auto max-w-4xl py-12 px-4 sm:px-6">
    <div v-if="store.isFetchingPlugin && !plugin" class="text-center py-12">
      <div class="animate-pulse flex flex-col items-center">
        <div class="h-12 w-12 bg-muted rounded-full mb-4"></div>
        <div class="h-6 w-1/3 bg-muted rounded mb-2"></div>
        <div class="h-4 w-1/4 bg-muted rounded"></div>
      </div>
    </div>

    <div v-else-if="store.fetchError && !plugin" class="text-center py-12 text-destructive">
      {{ store.fetchError }}
    </div>

    <div v-else-if="plugin" class="space-y-8">
      <PluginDetailHeader :plugin="plugin" />

      <PluginStatusNotice :plugin="plugin" />

      <PluginReadme
        :source-link="plugin.source_link"
        @loaded="scrollToHash"
        @collapsed="scrollToHash"
      />

      <!-- Comments Section -->
      <PluginComments
        v-if="showComments"
        :plugin-id="plugin.id"
        :total-comment-count="plugin.comment_count"
        @comment-added="onCommentAdded"
      />
    </div>
  </div>
</template>
