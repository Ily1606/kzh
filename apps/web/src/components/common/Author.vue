<script setup lang="ts">
import { RouterLink } from 'vue-router'

const props = withDefaults(
  defineProps<{
    name?: string | null;
    src?: string | null;
    fallback?: string;
    size?: 'sm' | 'md';
    username?: string | null;
  }>(),
  {
    name: null,
    src: null,
    fallback: 'community',
    size: 'sm',
    username: null,
  }
)

const SIZES = {
  sm: { box: 'size-5', text: 'text-[0.5625rem]' },
  md: { box: 'size-8', text: 'text-xs' },
} as const

const displayName = () => props.name || props.fallback

/** Hides a failed image so the initial letter underneath shows through. */
function hideBrokenImage(event: Event) {
  ;(event.target as HTMLElement).style.display = 'none'
}
</script>

<template>
  <span class="inline-flex items-center gap-1.5 min-w-0">
    <RouterLink
      v-if="username"
      :to="{ name: 'author-profile', params: { username } }"
      class="truncate transition-colors hover:text-primary hover:underline"
    >
      {{ displayName() }}
    </RouterLink>
    <span v-else class="truncate">{{ displayName() }}</span>
    <span
      class="relative hidden sm:inline-grid shrink-0 place-items-center overflow-hidden rounded-full bg-primary/10 font-bold leading-none text-primary"
      :class="[SIZES[size].box, SIZES[size].text]"
    >
      <img
        v-if="src"
        :src="src"
        :alt="displayName()"
        class="absolute inset-0 size-full object-cover"
        @error="hideBrokenImage"
      />
    </span>
  </span>
</template>
