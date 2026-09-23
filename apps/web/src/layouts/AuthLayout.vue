<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const title = computed(() => route.meta.title || 'Welcome')
</script>

<template>
  <div class="flex min-h-screen items-center justify-center bg-gray-50 p-4 dark:bg-[#111115]">
    <div class="w-full max-w-md">
      <div class="mb-8 text-center">
        <h1 class="text-3xl font-bold tracking-tight text-primary">DSH</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ title }}</p>
      </div>
      <slot></slot>
    </div>
  </div>
</template>

