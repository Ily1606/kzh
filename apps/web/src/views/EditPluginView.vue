<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { PluginResource, UpdatePluginRequest } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import PluginFieldsForm from '@/components/pages/plugin/PluginFieldsForm.vue'
import PluginStatusBadge from '@/components/pages/plugin/PluginStatusBadge.vue'
import { usePluginDetailAccess } from '@/composables/usePluginDetailAccess'
import { usePluginsStore } from '@/stores'
import { notifySuccess } from '@/utils/toast'
import type { PluginFields } from '@/schemas/plugin.schema'

const error = ref('')
const route = useRoute()
const router = useRouter()
const store = usePluginsStore()

const username = computed(() => route.params.username as string)
const pluginName = computed(() => route.params.pluginName as string)

const plugin = computed<PluginResource | null>(() => {
  if (username.value && pluginName.value) {
    return store.findBySlug(username.value, pluginName.value)
  }
  return null
})

const { canEdit } = usePluginDetailAccess(plugin)

const initialValues = computed<PluginFields | null>(() =>
  plugin.value
    ? {
        name: plugin.value.name,
        title: plugin.value.title,
        license: plugin.value.license as PluginFields['license'],
        category: plugin.value.category ?? '',
        tags: plugin.value.tags,
        source_link: plugin.value.source_link,
      }
    : null,
)

/**
 * Nothing here to edit.
 *
 * Only a pending plugin is editable
 */
function leave() {
  router.replace({
    name: 'plugin-detail',
    params: {
      username: username.value,
      pluginName: pluginName.value,
    },
  })
}

/** A bookmark or a new tab has no earlier entry, so `router.back()` would drop
 *  the reader out of the app. Go back only when there is somewhere to go. */
function goBack() {
  if (!window.history.state?.back) {
    leave()

    return
  }

  router.back()
}

async function save(values: PluginFields) {
  if (!plugin.value) return

  error.value = ''
  const saved = await store.updatePlugin(
    username.value,
    pluginName.value,
    values as UpdatePluginRequest
  )

  if (!saved) {
    error.value = 'Could not save the plugin. Please try again.'

    throw new Error(error.value)
  }

  notifySuccess('Plugin updated successfully.')

  // An edit can rename the plugin, so the URL that got us here may now 404.
  if (saved.author.username === username.value && saved.name === pluginName.value) {
    goBack()

    return
  }

  router.replace({
    name: 'plugin-detail',
    params: {
      username: saved.author.username,
      pluginName: saved.name,
    },
  })
}

onMounted(async () => {
  if (username.value && pluginName.value) {
    await store.ensurePluginBySlug(username.value, pluginName.value)
  }

  // Re-checked after the fetch: the store may have been empty on mount, and
  // ownership is only knowable once the plugin is actually in hand.
  if (!plugin.value || !canEdit.value) {
    leave()
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

    <div v-else-if="plugin && initialValues && canEdit" class="space-y-8">
      <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 pb-8 border-b">
        <div class="space-y-3">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold tracking-tight">Edit plugin</h1>
            <PluginStatusBadge :status="plugin.status" />
          </div>
          <p class="text-lg text-muted-foreground">{{ plugin.name }}</p>
        </div>

        <Button variant="outline" class="shrink-0" @click="router.back()">
          Back to plugin
        </Button>
      </div>

      <p class="text-sm text-muted-foreground">
        Saving these changes sends this plugin back into the review queue.
      </p>

      <PluginFieldsForm
        :initial-values="initialValues"
        submit-label="Save changes"
        :submit="save"
      />

      <div v-if="error" class="p-3.5 rounded-xl border border-destructive/30 bg-destructive/10 text-destructive text-sm">
        {{ error }}
      </div>
    </div>
  </div>
</template>
