<script setup lang="ts">
import { PopoverRoot } from 'reka-ui'

defineProps<{ class?: string }>()
</script>

<template>
  <PopoverRoot data-slot="popover" :class="$props.class">
    <slot />
  </PopoverRoot>
</template>
