<script setup lang="ts">
import { Motion } from "motion-v";
import type { IntrinsicElementAttributes } from "vue";

withDefaults(
  defineProps<{
    /**
     * Tag to render. Plain `string` is rejected by Motion's prop type, so this
     * is constrained to the tags Vue actually defines.
     */
    as?: keyof IntrinsicElementAttributes;
    delay?: number;
    /** Rise distance in px; 0 fades in place. */
    y?: number;
    duration?: number;
  }>(),
  { as: "div", delay: 0, y: 24, duration: 0.6 },
);
</script>

<template>
  <Motion
    :as="as"
    :initial="{ opacity: 0, y }"
    :while-in-view="{ opacity: 1, y: 0 }"
    :in-view-options="{ once: true, amount: 0.2 }"
    :transition="{ duration, delay, ease: [0.22, 1, 0.36, 1] }"
  >
    <slot />
  </Motion>
</template>
