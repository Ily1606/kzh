<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { cn } from '@/utils/cn'
defineOptions({ inheritAttrs: false })
const model = defineModel<string | number>()
defineProps<{ class?: HTMLAttributes['class'] }>()
</script>

<template>
  <input
    v-model="model"
    data-slot="input"
    :class="cn('file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground border-input flex h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs outline-none transition-[color,box-shadow] disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm', 'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50', 'aria-invalid:border-destructive aria-invalid:ring-destructive/20', $props.class)"
    v-bind="$attrs"
  >
</template>
