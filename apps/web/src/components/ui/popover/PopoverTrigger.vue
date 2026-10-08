<script setup lang="ts">
import { PopoverTrigger } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { cn } from '@/utils/cn'

defineProps<{ class?: HTMLAttributes['class'] }>()
</script>

<template>
  <!--
    Passes props straight through, `asChild` included. Do not force it here:
    a trigger that cannot render its own element is a trigger that only works
    one way, and the caller usually knows which it needs. A disabled `Button`
    carries `pointer-events: none` and cannot be the trigger itself, so that
    case passes `asChild` and lets this component render the wrapper instead.
  -->
  <PopoverTrigger data-slot="popover-trigger" :class="cn($props.class)">
    <slot />
  </PopoverTrigger>
</template>
