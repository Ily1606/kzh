<script setup lang="ts">
import { PopoverAnchor } from 'reka-ui'

defineProps<{ class?: string }>()
</script>

<template>
  <PopoverAnchor data-slot="popover-anchor" :class="$props.class">
    <slot />
  </PopoverAnchor>
</template>
