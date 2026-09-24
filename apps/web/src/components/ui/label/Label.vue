<script setup lang="ts">
import { Label as LabelPrimitive } from 'reka-ui'
import type { HTMLAttributes } from 'vue'
import { cn } from '@/utils/cn'
defineOptions({ inheritAttrs: false })
defineProps<{ class?: HTMLAttributes['class'] }>()
</script>

<template>
  <LabelPrimitive data-slot="label" :class="cn('text-sm leading-none font-medium', $props.class)" v-bind="$attrs">
    <slot />
  </LabelPrimitive>
</template>
