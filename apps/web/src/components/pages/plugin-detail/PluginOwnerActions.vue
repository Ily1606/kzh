<script setup lang="ts">
import { toRef } from 'vue'
import { RouterLink } from 'vue-router'
import { Pencil } from 'lucide-vue-next'
import type { PluginResource } from '@/api/generated/model'
import { Button } from '@/components/ui/button'
import { usePluginDetailAccess } from '@/composables/usePluginDetailAccess'

const props = defineProps<{
  plugin: PluginResource
}>()

const { canEdit } = usePluginDetailAccess(toRef(props, 'plugin'))
</script>

<template>
  <!-- Rendered only when an edit is possible -->
  <Button
    v-if="canEdit"
    as-child
    class="flex-1 cursor-pointer gap-2 font-medium border border-gray-400 bg-background text-foreground hover:bg-foreground hover:text-background"
  >
    <RouterLink :to="{ name: 'plugin-edit', params: { id: plugin.id } }">
      <Pencil class="size-4" />
      Edit plugin
    </RouterLink>
  </Button>
</template>
