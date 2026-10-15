<script setup lang="ts">
import type { HTMLAttributes } from "vue"
import type { CardVariants } from "."
import { cardVariants } from "."
import { cn } from '@/utils/cn'

const props = defineProps<{
  variant?: CardVariants["variant"]
  class?: HTMLAttributes["class"]
}>()
</script>

<template>
  <div
    data-slot="card"
    :class="
      cn(
        cardVariants({ variant: props.variant }),
        props.class,
      )
    "
  >
    <slot />
  </div>
</template>
