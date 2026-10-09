<script setup lang="ts">
import { computed } from 'vue'
import { Badge } from '@/components/ui/badge'

type BadgeVariant = {
  label: string
  class: string
}

const props = defineProps<{
  /** Raw `status` from the API. See `PluginStatus` on the backend. */
  status: string
}>()


const VARIANTS: Record<string, BadgeVariant> = {
  approved: {
    label: 'Published',
    class: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/20',
  },
  pending: {
    label: 'Pending',
    class: 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20',
  },
  rejected: {
    label: 'Rejected',
    class: 'bg-destructive/10 text-destructive border-destructive/20',
  },
}

/*
  An unknown status falls back to the neutral muted badge showing the raw
  value. A status the backend adds later should degrade to "unlabelled but
  present", never to an empty cell that reads as missing data.
*/
const variant = computed<BadgeVariant>(() => VARIANTS[props.status] ?? {
  label: props.status,
  class: 'bg-muted text-muted-foreground border-border',
})
</script>

<template>
  <Badge variant="outline" :class="variant.class">
    {{ variant.label }}
  </Badge>
</template>
