<script setup lang="ts">
import { ref, toRef } from 'vue'
import { useRouter, RouterLink } from 'vue-router'
import { Pencil, Trash2 } from '@lucide/vue'
import type { PluginResource } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import { usePluginDetailAccess } from '@/composables/usePluginDetailAccess'
import { usePluginsStore } from '@/stores'
import { notifySuccess } from '@/utils/toast'
import PluginDeleteModal from './PluginDeleteModal.vue'

const props = defineProps<{
  plugin: PluginResource
}>()

const router = useRouter()
const store = usePluginsStore()

const { canEdit, canDelete } = usePluginDetailAccess(toRef(props, 'plugin'))

const showDeleteModal = ref(false)
const isDeleting = ref(false)

async function handleDelete() {
  if (isDeleting.value) return
  isDeleting.value = true

  try {
    const success = await store.deletePlugin(props.plugin.id)
    if (success) {
      showDeleteModal.value = false
      notifySuccess('Plugin deleted successfully.')
      await router.push({ name: 'my-plugins' })
    }
  } finally {
    isDeleting.value = false
  }
}
</script>

<template>
  <!-- Rendered only when an edit is possible -->
  <Button
    v-if="canEdit"
    as-child
    variant="outline"
    class="flex-1 cursor-pointer gap-2 font-medium border-gray-400 bg-background text-foreground hover:bg-foreground hover:text-background"
  >
    <RouterLink :to="{ name: 'plugin-edit', params: { id: plugin.id } }">
      <Pencil class="size-4" />
      Edit plugin
    </RouterLink>
  </Button>

  <!-- Rendered only when user is the owner -->
  <Button
    v-if="canDelete"
    type="button"
    variant="outline"
    class="flex-1 cursor-pointer gap-2 font-medium border-destructive/40 text-destructive hover:bg-destructive hover:text-white dark:border-destructive/50 dark:hover:bg-destructive dark:hover:text-white"
    @click="showDeleteModal = true"
  >
    <Trash2 class="size-4" />
    Delete plugin
  </Button>

  <PluginDeleteModal
    :show="showDeleteModal"
    :plugin-name="plugin.name"
    :is-deleting="isDeleting"
    @close="showDeleteModal = false"
    @confirm="handleDelete"
  />
</template>
