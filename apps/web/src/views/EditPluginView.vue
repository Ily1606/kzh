<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter, RouterLink } from 'vue-router'
import type { PluginResource, UpdatePluginRequest } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import PluginFieldsForm from '@/components/pages/plugin/PluginFieldsForm.vue'
import PluginStatusBadge from '@/components/pages/plugin/PluginStatusBadge.vue'
import { usePluginDetailAccess } from '@/composables/usePluginDetailAccess'
import { usePluginsStore } from '@/stores'
import { notifySuccess } from '@/utils/toast'
import type { PluginFields } from '@/schemas/plugin.schema'
import { updatePluginSchema } from '@/schemas/plugin.schema'

const error = ref('')
const route = useRoute()
const router = useRouter()
const store = usePluginsStore()

const pluginId = computed(() => route.params.id as string)
const plugin = computed<PluginResource | null>(() => store.findById(pluginId.value))

const { canEdit } = usePluginDetailAccess(plugin)

const initialValues = computed<PluginFields | null>(() =>
  plugin.value
    ? {
        name: plugin.value.name,
        title: plugin.value.title,
        license: plugin.value.license as PluginFields['license'],
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
  router.replace({ name: 'plugin-detail', params: { id: pluginId.value } })
}

async function save(values: PluginFields) {
  error.value = ''
  const saved = await store.updatePlugin(pluginId.value, values as UpdatePluginRequest)

  if (!saved) {
    error.value = 'Could not save the plugin. Please try again.'

    throw new Error(error.value)
  }

  notifySuccess('Plugin updated successfully.')

  router.push({ name: 'plugin-detail', params: { id: pluginId.value } })
}

onMounted(async () => {
  await store.ensurePlugin(pluginId.value)

  // Re-checked after the fetch: the store may have been empty on mount, and
  // ownership is only knowable once the plugin is actually in hand.
  if (!plugin.value || !canEdit.value) {
    leave()
  }
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

    <div v-else-if="plugin && initialValues && canEdit" class="space-y-8">
      <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 pb-8 border-b">
        <div class="space-y-3">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-3xl font-bold tracking-tight">Edit plugin</h1>
            <PluginStatusBadge :status="plugin.status" />
          </div>
          <p class="text-lg text-muted-foreground">{{ plugin.name }}</p>
        </div>

        <Button as-child variant="outline" class="shrink-0">
          <RouterLink :to="{ name: 'plugin-detail', params: { id: plugin.id } }">
            Back to plugin
          </RouterLink>
        </Button>
      </div>

      <p class="text-sm text-muted-foreground">
        Saving these changes sends this plugin back into the review queue.
      </p>

      <PluginFieldsForm
        :schema="updatePluginSchema"
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
