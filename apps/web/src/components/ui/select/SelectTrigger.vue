<script setup lang="ts">
import { SelectIcon, SelectTrigger, type SelectTriggerProps } from 'reka-ui'
import { ChevronDown } from 'lucide-vue-next'
import type { HTMLAttributes } from 'vue'
import { cn } from '@/utils/cn'

const props = withDefaults(defineProps<SelectTriggerProps & { class?: HTMLAttributes['class'] }>(), {
  disabled: undefined,
})
</script>

<template>
  <SelectTrigger
    data-slot="select-trigger"
    v-bind="{ ...props, class: undefined }"
    :class="cn('border-input data-[placeholder]:text-muted-foreground flex w-full items-center justify-between gap-2 rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none', 'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50', 'aria-invalid:border-destructive aria-invalid:ring-destructive/20', '[&_svg]:pointer-events-none [&_svg]:shrink-0 [&_svg:not([class*=size-])]:size-4', '*:data-[slot=select-value]:line-clamp-1 *:data-[slot=select-value]:flex *:data-[slot=select-value]:items-center *:data-[slot=select-value]:gap-2', props.class)"
  >
    <slot />
    <SelectIcon as-child>
      <ChevronDown class="size-4 opacity-50" />
    </SelectIcon>
  </SelectTrigger>
</template>