<script setup lang="ts">

const props = withDefaults(
  defineProps<{
    name?: string | null;
    src?: string | null;
    fallback?: string;
    size?: 'sm' | 'md';
  }>(),
  {
    name: null,
    src: null,
    fallback: 'community',
    size: 'sm',
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
    <span class="truncate">{{ displayName() }}</span>
    <span
      class="relative hidden @xs:inline-grid shrink-0 place-items-center overflow-hidden rounded-full bg-primary/10 font-bold leading-none text-primary"
      :class="[SIZES[size].box, SIZES[size].text]"
    >
      <span class="grid place-items-center">{{ displayName().charAt(0).toUpperCase() }}</span>
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
