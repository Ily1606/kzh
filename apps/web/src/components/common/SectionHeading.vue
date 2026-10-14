<script setup lang="ts">
withDefaults(
  defineProps<{
    eyebrow?: string;
    title: string;
    description?: string;
    align?: "left" | "center";
  }>(),
  { align: "left" },
);
</script>

<template>
  <div :class="align === 'center' ? 'text-center' : ''">
    <p v-if="eyebrow" class="text-sm font-medium text-primary">{{ eyebrow }}</p>
    <h2
      class="text-3xl font-semibold tracking-tight"
      :class="eyebrow ? 'mt-2' : ''"
    >
      <slot name="title">{{ title }}</slot>
    </h2>
    <p
      v-if="description || $slots.description"
      class="mt-2 text-muted-foreground"
      :class="align === 'center' ? 'mx-auto max-w-2xl' : 'max-w-xl'"
    >
      <slot name="description">{{ description }}</slot>
    </p>
  </div>
</template>
