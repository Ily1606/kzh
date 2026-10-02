<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority'
import type { HTMLAttributes } from 'vue'
import { cn } from '@/utils/cn'
const alertVariants = cva('relative w-full rounded-lg border px-4 py-3 text-sm', {
  variants: {
    variant: {
      default: 'bg-card text-card-foreground',
      destructive: 'border-destructive/50 bg-card text-destructive',
    },
  },
  defaultVariants: { variant: 'default' },
})
type AlertVariants = VariantProps<typeof alertVariants>
defineProps<{ variant?: AlertVariants['variant']; class?: HTMLAttributes['class'] }>()
</script>

<template>
  <div data-slot="alert" role="alert" :class="cn(alertVariants({ variant: $props.variant }), $props.class)"><slot /></div>
</template>
