<script setup lang="ts">
import PluginComments from '@/components/pages/comment/PluginComments.vue'
import PluginDetailHeader from '@/components/pages/plugin-detail/PluginDetailHeader.vue'
import PluginReadme from '@/components/pages/plugin-detail/PluginReadme.vue'
import PluginStatusNotice from '@/components/pages/plugin-detail/PluginStatusNotice.vue'
import StarTimeline from '@/components/pages/plugin/StarTimeline.vue'
import { usePluginDetailAccess } from '@/composables/usePluginDetailAccess'
import { usePluginsStore } from '@/stores'
import { computed, nextTick, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'

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
  if (pluginId.value) {
    await store.ensurePlugin(pluginId.value)
    void store.trackView(pluginId.value)
  }
  scrollToHash()
})

watch(pluginId, async (newId, oldId) => {
  if (newId && newId !== oldId) {
    await store.ensurePlugin(newId)
    void store.trackView(newId)
  }
})
</script>

<template>
  <div class="mx-auto max-w-4xl py-12 px-4 sm:px-6">
    <div v-if="store.isFetchingPlugin && !plugin" class="glass animate-pulse rounded-2xl py-12 text-center">
      <div class="flex flex-col items-center">
        <div class="h-12 w-12 bg-muted rounded-full mb-4"></div>
        <div class="h-6 w-1/3 bg-muted rounded mb-2"></div>
        <div class="h-4 w-1/4 bg-muted rounded"></div>
      </div>
    </div>

    <div v-else-if="store.fetchError && !plugin" class="glass rounded-2xl py-12 text-center text-destructive">
      {{ store.fetchError }}
    </div>

    <div v-else-if="plugin" class="space-y-8">
      <PluginDetailHeader :plugin="plugin" />

      <PluginStatusNotice :plugin="plugin" />

      <StarTimeline
        :plugin-id="plugin.id"
        :star-count="plugin.star_count"
        :is-starred="plugin.is_star"
      />

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
