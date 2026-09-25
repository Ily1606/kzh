<script setup lang="ts">
import { RouterLink } from 'vue-router'
import Button from '@/components/ui/Button.vue'
</script>

<template>
  <div class="flex h-[60vh] flex-col items-center justify-center text-center">
    <h1 class="text-9xl font-extrabold tracking-tight text-gray-200 dark:text-gray-800">404</h1>
    <h2 class="mt-4 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl dark:text-gray-100">Page not found</h2>
    <p class="mt-2 text-lg text-gray-500 dark:text-gray-400">Sorry, we couldn't find the page you're looking for.</p>
    <div class="mt-8">
      <RouterLink to="/">
        <Button>Go back home</Button>
      </RouterLink>
    </div>
  </div>
</template>

